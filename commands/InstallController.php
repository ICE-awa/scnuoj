<?php

namespace app\commands;

use Yii;
use yii\console\Controller;

class InstallController extends Controller
{
    const DEFAULT_ADMIN_USERNAME = 'admin';
    const DEFAULT_ADMIN_PASSWORD = 'admin';
    const DEFAULT_ADMIN_EMAIL = 'admin@localhost';

    public function actionIndex()
    {
        echo "================================================\n";
        echo " SCNU Online Judge Initialization Tool v1.0\n";
        echo "================================================\n\n";

        $this->prepareEnvironment();
        $this->configureJudgeAndPolygon();

        echo "\n================================================";
        echo "\nRun: php yii migrate";
        echo "\n================================================\n";
        $exitCode = $this->runCommand($this->yiiCommand('migrate'));
        if ($exitCode !== 0) {
            return $exitCode;
        }

        $this->startSocket();
        echo "\nInitialization completed.\n\n";
        return 0;
    }

    public function actionAuto()
    {
        echo "================================================\n";
        echo " SCNU Online Judge Docker Auto Initialization\n";
        echo "================================================\n\n";

        $this->setAutoInstallDefaults();
        $this->prepareEnvironment();
        $this->configureJudgeAndPolygon();

        if ($this->isDatabaseInitialized()) {
            echo "Database already initialized. Skip migrations.\n";
            $this->startSocket();
            return 0;
        }

        echo "\n================================================";
        echo "\nDatabase is empty. Run: php yii migrate --interactive=0";
        echo "\n================================================\n";
        $exitCode = $this->runCommand($this->yiiCommand('migrate --interactive=0'));
        if ($exitCode !== 0) {
            return $exitCode;
        }

        $this->startSocket();
        echo "\nAuto initialization completed.\n\n";
        return 0;
    }

    private function prepareEnvironment()
    {
        $root = str_replace('\\', '/', __DIR__ . '/..');
        $env = [
            'setWritable' => [
                'runtime',
                'web/assets',
                'web/uploads',
                'polygon/log',
                'polygon/data',
                'judge/data',
                'judge/log',
            ],
            'setCookieValidationKey' => [
                'config/web.php',
            ],
        ];
        $callbacks = ['setCookieValidationKey', 'setWritable', 'setExecutable'];
        foreach ($callbacks as $callback) {
            if (!empty($env[$callback])) {
                $this->$callback($root, $env[$callback]);
            }
        }
    }

    private function configureJudgeAndPolygon()
    {
        $parts = $this->parseDsn(Yii::$app->db->dsn);
        $socket = trim((string) getenv('SCNUOJ_JUDGE_DB_SOCKET'));
        $host = $socket === ''
            ? ($parts['host'] ?? 'mysql')
            : (getenv('SCNUOJ_JUDGE_DB_HOST') ?: 'localhost');
        $dbname = $parts['dbname'] ?? 'scnuoj';

        $this->setConfig('judge/config.ini', 'OJ_HOST_NAME', $host);
        $this->setConfig('judge/config.ini', 'OJ_USER_NAME', Yii::$app->db->username);
        $this->setConfig('judge/config.ini', 'OJ_PASSWORD', Yii::$app->db->password);
        $this->setConfig('judge/config.ini', 'OJ_DB_NAME', $dbname);
        $this->setConfig('judge/config.ini', 'OJ_MYSQL_UNIX_PORT', $socket);
        $this->setOptionalConfig('judge/config.ini', 'OJ_USE_PTRACE', getenv('SCNUOJ_JUDGE_USE_PTRACE'));
        $this->setConfig('polygon/config.ini', 'OJ_HOST_NAME', $host);
        $this->setConfig('polygon/config.ini', 'OJ_USER_NAME', Yii::$app->db->username);
        $this->setConfig('polygon/config.ini', 'OJ_PASSWORD', Yii::$app->db->password);
        $this->setConfig('polygon/config.ini', 'OJ_DB_NAME', $dbname);
        $this->setConfig('polygon/config.ini', 'OJ_MYSQL_UNIX_PORT', $socket);
        $this->setOptionalConfig('polygon/config.ini', 'OJ_USE_PTRACE', getenv('SCNUOJ_JUDGE_USE_PTRACE'));
    }

    private function isDatabaseInitialized()
    {
        try {
            return Yii::$app->db->schema->getTableSchema('{{%user}}', true) !== null;
        } catch (\Throwable $e) {
            echo "Unable to inspect database schema: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function setAutoInstallDefaults()
    {
        $defaults = [
            'SCNUOJ_AUTO_INSTALL' => '1',
            'SCNUOJ_ADMIN_USERNAME' => self::DEFAULT_ADMIN_USERNAME,
            'SCNUOJ_ADMIN_PASSWORD' => self::DEFAULT_ADMIN_PASSWORD,
            'SCNUOJ_ADMIN_EMAIL' => self::DEFAULT_ADMIN_EMAIL,
            'SCNUOJ_JUDGE_DB_SOCKET' => '/judge/mysqld/mysqld.sock',
            'SCNUOJ_JUDGE_USE_PTRACE' => '0',
        ];

        foreach ($defaults as $name => $value) {
            $current = getenv($name);
            if ($current === false || $current === '') {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    private function startSocket()
    {
        echo "\n================================================";
        echo "\nRun: php socket.php start -d";
        echo "\n================================================\n";
        $exitCode = $this->runCommand($this->phpCommand('socket.php start -d'));
        if ($exitCode !== 0) {
            echo "Warning: socket service failed to start. Continue php-fpm startup.\n";
        }
    }

    private function parseDsn($dsn)
    {
        $result = [];
        $pos = strpos($dsn, ':');
        $body = $pos === false ? $dsn : substr($dsn, $pos + 1);
        foreach (explode(';', $body) as $part) {
            if (strpos($part, '=') === false) {
                continue;
            }
            list($key, $value) = explode('=', $part, 2);
            $result[trim($key)] = trim($value);
        }
        return $result;
    }

    private function yiiCommand($arguments)
    {
        return $this->phpCommand('yii ' . $arguments);
    }

    private function phpCommand($arguments)
    {
        return escapeshellarg(PHP_BINARY) . ' ' . $arguments;
    }

    private function runCommand($command)
    {
        passthru($command, $exitCode);
        return $exitCode;
    }

    private function setConfig($file, $key, $value)
    {
        $str = file_get_contents($file);
        $line = $key . "=" . $value;
        $pattern = "/^#?" . preg_quote($key, "/") . "=.*/m";
        if (preg_match($pattern, $str)) {
            $str2 = preg_replace($pattern, $line, $str);
        } else {
            $str2 = rtrim($str) . "\n" . $line . "\n";
        }
        file_put_contents($file, $str2);
    }

    private function setOptionalConfig($file, $key, $value)
    {
        if ($value === false || trim((string) $value) === '') {
            return;
        }

        $this->setConfig($file, $key, trim((string) $value));
    }

    private function setWritable($root, $paths)
    {
        foreach ($paths as $writable) {
            echo "   chmod 0777 $writable\n";
            @chmod("$root/$writable", 0777);
        }
    }

    private function setExecutable($root, $paths)
    {
        foreach ($paths as $executable) {
            echo "   chmod 0755 $executable\n";
            @chmod("$root/$executable", 0755);
        }
    }

    private function setCookieValidationKey($root, $paths)
    {
        foreach ($paths as $file) {
            echo "   generate cookie validation key in $file\n";
            $file = $root . '/' . $file;
            $length = 32;
            $bytes = openssl_random_pseudo_bytes($length);
            $key = strtr(substr(base64_encode($bytes), 0, $length), '+/=', '_-.');
            $content = preg_replace('/(("|\')cookieValidationKey("|\')\s*=>\s*)(""|\'\')/', "\\1'$key'", file_get_contents($file));
            file_put_contents($file, $content);
        }
    }
}
