<?php
/** Release 0.4.3: public .apk URLs compatible with Fire TV Downloader. */
declare(strict_types=1);
return static function(PDO $pdo):void {
    require_once ROOT.'/app/apk_releases.php';
    $rows=$pdo->query("SELECT * FROM app_releases WHERE platform='android-tv' AND status='published'")->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $row)ds_apk_publish_static($row);
    ds_apk_refresh_shortcut($pdo);
    $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.3')");
};
