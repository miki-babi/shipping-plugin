<?php
// Define the path to your keys
$privateKey = file_get_contents(__DIR__ . '/private.pem');
$publicKeyDer = base64_encode(file_get_contents(__DIR__ . '/public.der'));

if (isset($_GET['msg'])) {
    // Decrypting the message in PHP
    $encrypted = base64_decode($_GET['msg']);
    $success = openssl_private_decrypt($encrypted, $decrypted, $privateKey, OPENSSL_PKCS1_OAEP_PADDING);

    if ($success) {
        echo "✅ Decrypted Message is :" . htmlspecialchars($decrypted);
    } else {
        echo "❌ Decryption failed.<br>";

        // Show OpenSSL error messages
        while ($msg = openssl_error_string()) {
            echo "🛠️ OpenSSL error: " . htmlspecialchars($msg) . "<br>";
        }
    }
} else {
    echo "No message provided.";
}
?>

<!DOCTYPE html>
<html>
<head><title>RSA Encrypt</title></head>
<body>
<script>
// Check if there's already a 'msg' parameter, if so, skip encryption
if (!window.location.search.includes('msg=')) {
    document.addEventListener("DOMContentLoaded", async () => {
        const message = "Hello, oooooo!"; // The message to encrypt
        const publicKeyBase64 = `<?= $publicKeyDer ?>`; // Get the public key from PHP

        // Helper function to convert base64 to ArrayBuffer
        function base64ToArrayBuffer(base64) {
            const binary = atob(base64);
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            return bytes.buffer;
        }

        // Convert the base64 public key into an ArrayBuffer for encryption
        const keyBuffer = base64ToArrayBuffer(publicKeyBase64);

        // Import the public key for encryption
        const publicKey = await window.crypto.subtle.importKey(
            "spki",
            keyBuffer,
            {
                name: "RSA-OAEP",
                hash: "SHA-1"  // Match SHA-1 hash to PHP
            },
            false,
            ["encrypt"]
        );

        // Encode the message as bytes
        const encoder = new TextEncoder();
        const encoded = encoder.encode(message);

        // Encrypt the message with the public key
        const encrypted = await window.crypto.subtle.encrypt(
            { name: "RSA-OAEP" },
            publicKey,
            encoded
        );

        // Convert the encrypted data to base64
        const encryptedB64 = btoa(String.fromCharCode(...new Uint8Array(encrypted)));

        // Redirect to the same page with the encrypted message in the URL
        window.location.href = "ss.php?msg=" + encodeURIComponent(encryptedB64);
    });
}
</script>
</body>
</html>
