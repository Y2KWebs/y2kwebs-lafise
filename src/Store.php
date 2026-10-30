<?php
namespace Y2KWebs\Lafise;

/** Durable attempts plus connection-scoped MySQL locks. Never expire a lock while a bank call is running. */
final class Store
{
    private string $table;
    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'y2k_lafise_attempts';
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'y2k_lafise_attempts';
        $collate = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint unsigned NOT NULL,
            reference varchar(32) NOT NULL,
            ticket_hash varchar(64) NOT NULL,
            state varchar(24) NOT NULL,
            data longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY reference (reference),
            UNIQUE KEY ticket_hash (ticket_hash),
            KEY order_id (order_id)
        ) $collate;");
        update_option('y2k_lafise_schema', 1, false);
    }

    public function locked(int $orderId, callable $work): mixed
    {
        global $wpdb;
        $name = 'y2kl:' . hash('sha256', $this->table . ':' . $orderId);
        $name = substr($name, 0, 64);
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $name)) !== '1') {
            throw new \RuntimeException('Operation already in progress.');
        }
        try {
            return $work();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }

    public function latest(int $orderId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE order_id = %d ORDER BY id DESC LIMIT 1", $orderId), ARRAY_A);
        return $row ? $this->decode($row) : null;
    }

    public function find(string $reference): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE reference = %s", $reference), ARRAY_A);
        return $row ? $this->decode($row) : null;
    }

    private function decode(array $row): array
    {
        $row['data'] = Secrets::open($row['data']);
        return $row;
    }

    public function create(int $orderId, array $data): array
    {
        global $wpdb;
        $reference = 'Y2K' . strtoupper(bin2hex(random_bytes(8)));
        $now = gmdate('Y-m-d H:i:s');
        $ok = $wpdb->insert($this->table, ['order_id' => $orderId, 'reference' => $reference, 'ticket_hash' => $data['ticket_hash'], 'state' => 'sending',
            'data' => Secrets::seal($data), 'created_at' => $now, 'updated_at' => $now]);
        if ($ok !== 1) {
            throw new \RuntimeException('Cannot persist payment attempt.');
        }
        return $this->find($reference) ?? throw new \RuntimeException('Cannot retrieve payment attempt.');
    }

    public function save(array $attempt, string $state, array $data): array
    {
        global $wpdb;
        if ($wpdb->update($this->table, ['state' => $state, 'data' => Secrets::seal($data), 'updated_at' => gmdate('Y-m-d H:i:s')],
            ['id' => $attempt['id']]) === false) {
            throw new \RuntimeException('Cannot persist bank result.');
        }
        return array_merge($attempt, ['state' => $state, 'data' => $data]);
    }
}
