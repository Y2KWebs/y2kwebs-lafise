import { test } from 'node:test';
import assert from 'node:assert/strict';
import { webcrypto } from 'node:crypto';
import { encryptCard } from '../assets/jwe.mjs';

test('JWE decrypts with independent WebCrypto recipient and binds protected header', async () => {
    const crypto = webcrypto;
    const pair = await crypto.subtle.generateKey({name:'RSA-OAEP', modulusLength:2048, publicExponent:new Uint8Array([1,0,1]), hash:'SHA-256'}, true, ['encrypt','decrypt']);
    const publicKey = await crypto.subtle.exportKey('jwk',pair.publicKey);
    const card = {accountNumber:'SYNTHETIC-NOT-A-CARD',cvv2:'TEST',name:'Fixture'};
    const encoded = await encryptCard(card,publicKey);
    const [header, wrapped, iv, ciphertext, tag] = encoded.split('.');
    assert.deepEqual(JSON.parse(Buffer.from(header,'base64url')), {alg:'RSA-OAEP-256',enc:'A256GCM'});
    const raw = await crypto.subtle.decrypt('RSA-OAEP',pair.privateKey,Buffer.from(wrapped,'base64url'));
    const key = await crypto.subtle.importKey('raw',raw,'AES-GCM',false,['decrypt']);
    const payload = Buffer.concat([Buffer.from(ciphertext,'base64url'),Buffer.from(tag,'base64url')]);
    const options = {name:'AES-GCM',iv:Buffer.from(iv,'base64url'),additionalData:Buffer.from(header),tagLength:128};
    const plain = await crypto.subtle.decrypt(options,key,payload);
    assert.deepEqual(JSON.parse(Buffer.from(plain).toString()),card);
    await assert.rejects(crypto.subtle.decrypt({...options,additionalData:Buffer.from('tampered')},key,payload));
    assert.notEqual(await encryptCard(card,publicKey),encoded);
});

test('rejects incompatible key and invalid kid',async()=>{
    await assert.rejects(encryptCard({}, {kty:'EC'}));
    await assert.rejects(encryptCard({}, {kty:'RSA'}, ''));
});
