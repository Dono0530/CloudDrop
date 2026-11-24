<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/Database.php';

$db = Database::getInstance();
$purged = [];
$purged['share_access_log'] = $db->execute(
    "DELETE FROM share_access_log WHERE accessed_at < DATE_SUB(NOW(), INTERVAL 180 DAY)"
);
$purged['activity_log'] = $db->execute(
    "DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)"
);
$purged['login_attempts'] = $db->execute(
    "DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 180 DAY)"
);
$purged['share_password_attempts'] = $db->execute(
    "DELETE FROM share_password_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"
);

foreach ($purged as $table => $count) {
    echo $table . ': ' . $count . PHP_EOL;
}
