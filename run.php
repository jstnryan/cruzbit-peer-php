<?php

use cruzbit\Database;
use cruzbit\Log;
use cruzbit\Peer;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Factory;
use React\Socket\Connector;

error_reporting(-1);
ini_set('error_log', 'syslog');
ini_set('html_errors', false);
ini_set('display_errors', true);
ini_set('date.timezone', 'Etc/UTC');
date_default_timezone_set('Etc/UTC');

require __DIR__ . '/settings.php';

if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') {
    if ($_SERVER['argc'] > 1) {
        $args = $_SERVER['argv'];
        array_shift($args); //don't care about the script name
        for ($a = 1; $a < count($_SERVER['argv']); $a++) {
            switch ($_SERVER['argv'][$a]) {
                case '-s':
                case '--silent':
                    $settings['noise_level'] = 0;
                    break;
                case '-q':
                case '--quiet':
                    $settings['noise_level'] = 1;
                    break;
                case '-v':
                case '--verbose':
                    $settings['noise_level'] = 3;
                    break;
                case '-d':
                case '--debug':
                    $settings['noise_level'] = 4;
                    break;
                case '--noise-level':
                    $settings['noise_level'] = (int) $_SERVER['argv'][++$a];
                    break;
                default:
                    exit("Unknown option supplied: \"{$_SERVER['argv'][$a]}. Aborting.\n");
            }

        }
    }
} else {
    exit('This application must be run from command line.');
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/constants.php';

$log = new Log($settings['noise_level']);
$peer = new Peer(
    new Database(
        $settings['database']['host'],
        $settings['database']['database'],
        $settings['database']['user'],
        $settings['database']['password'],
        $log
    ),
    $log
);

$loop = Factory::create();
$reactConnector = new Connector($loop, [
    'dns' => '1.1.1.1',
    'timeout' => 10
]);
$connector = new \Ratchet\Client\Connector($loop, $reactConnector);
$connector(
    'wss://' . $settings['socket']['peer_address'] . '/' . $settings['socket']['genesis_block'],
    $settings['socket']['protocols'],
    ['Origin' => $settings['socket']['origin']]
)->then(function(WebSocket $connection) use ($loop, $peer, $log) {
    $peer->storeConnection($connection);

    $connection->on('close', function($code = null, $reason = null) use ($loop, $log) {
        $log->write(2, "Received close message");
        $loop->stop();
    });

    $connection->on('message', function(MessageInterface $msg) use ($peer, $connection, $log) {
        if ($peer->onMessage($msg) === false) {
            $log->write(4, "WebSocket message event method failed; closing connection");
            $connection->close();
        }
    });

    // send a new peer a request to find a common ancestor
    if (!$peer->sendFindCommonAncestor(null, true)) {
        $log->write(2, "Error sending find_common_ancestor");
        $connection->close();
    }
},
function(Exception $e) use ($loop, $log) {
    $log->write(1, "Could not connect: {$e->getMessage()}\n");
    $loop->stop();
});

$loop->run();
