<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=scnuoj',
    'username' => getenv('DB_USERNAME') ?: 'socoding',
    'password' => getenv('DB_PASSWORD') ?: 'socoding',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 60,
    'schemaCache' => 'cache',
];
