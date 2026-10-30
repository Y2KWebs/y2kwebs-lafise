<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
// Keep financial audit records, settings and pending attempts. Deletion requires a deliberate backup/retention procedure.
wp_unschedule_hook('y2k_lafise_reconcile');
