<?php
/** Device PIN commands never appear in shared config or backup data. */
declare(strict_types=1);
return static function(PDO $pdo):void{
    $previous=require ROOT.'/database/migrations/0.4.30.php';$previous($pdo);
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_pin_commands(
        device_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        tenant_id BIGINT UNSIGNED NOT NULL,
        revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
        token_binding CHAR(64) NOT NULL,
        ciphertext BLOB NULL,iv VARBINARY(12) NULL,auth_tag VARBINARY(16) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        requested_by BIGINT UNSIGNED NOT NULL,
        requested_at DATETIME NOT NULL,expires_at DATETIME NOT NULL,applied_at DATETIME NULL,
        INDEX idx_device_pin_tenant(tenant_id,device_id),
        FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE,
        FOREIGN KEY(tenant_id) REFERENCES tenants(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Explicit 2026 test-key renewal. Migrate only the known old built-in
    // Android-TV pin; never overwrite a customer-managed different certificate.
    $old='5390caf27181bb1163bb165867be5a5a83e748899957f0e0f68606ed6282f47a';
    $new='52468eda51fbe7de6886fd800874f73566bdaa7165732e96f03764483840ec2f';
    $pin=$pdo->prepare("UPDATE apk_repository_sources SET certificate_sha256=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE platform='android-tv' AND repository='scammarano/2camdigitaltvweb' AND certificate_sha256=?");
    $pin->execute([$new,$old]);
    $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.31')");
};
