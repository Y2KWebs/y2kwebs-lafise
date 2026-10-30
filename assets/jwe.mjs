const encode = (value) => btoa(String.fromCharCode(...new Uint8Array(value)))
    .replaceAll('+', '-').replaceAll('/', '_').replaceAll('=', '');

export async function encryptCard(card, publicKey, keyId = null) {
    if (!globalThis.crypto?.subtle || publicKey?.kty !== 'RSA'
        || (keyId !== null && (typeof keyId !== 'string' || !keyId || keyId.length > 255))) {
        throw new Error('Encryption unavailable');
    }
    const crypto = globalThis.crypto;
    const encoder = new TextEncoder();
    const header = encode(encoder.encode(JSON.stringify({ alg: 'RSA-OAEP-256', enc: 'A256GCM',
        ...(keyId === null ? {} : { kid: keyId }) })));
    const key = await crypto.subtle.importKey('jwk', publicKey,
        { name: 'RSA-OAEP', hash: 'SHA-256' }, false, ['encrypt']);
    const contentKey = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt']);
    const rawKey = new Uint8Array(await crypto.subtle.exportKey('raw', contentKey));
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const plaintext = encoder.encode(JSON.stringify(card));
    try {
        const wrapped = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, key, rawKey);
        const encrypted = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-GCM', iv,
            additionalData: encoder.encode(header), tagLength: 128 }, contentKey, plaintext));
        return [header, encode(wrapped), encode(iv), encode(encrypted.slice(0, -16)), encode(encrypted.slice(-16))].join('.');
    } finally {
        rawKey.fill(0);
        plaintext.fill(0);
    }
}
