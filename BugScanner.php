<?php

// Required libraries for parallel processing and networking
if (!function_exists('pcntl_fork')) {
    die("This script requires the PCNTL extension for parallel processing.\n");
}

class BugScanner {
    protected $threads;
    protected $tasks = [];
    protected $processes = [];

    public function __construct($threads = 25) {
        $this->threads = $threads;
    }

    protected function convertHostPort($host, $port) {
        return ($port == '80' || $port == '443') ? $host : "$host:$port";
    }

    protected function getUrl($host, $port, $uri = null) {
        $protocol = $port == '443' ? 'https' : 'http';
        return "$protocol://" . $this->convertHostPort($host, $port) . ($uri ? "/$uri" : '');
    }

    protected function log($message) {
        echo $message . "\n";
    }

    public function addTask($task) {
        $this->tasks[] = $task;
    }

    public function runTasks() {
        foreach ($this->tasks as $task) {
            if (count($this->processes) >= $this->threads) {
                $this->waitForProcess();
            }
            $pid = pcntl_fork();
            if ($pid == -1) {
                die("Could not fork process.\n");
            } elseif ($pid) {
                $this->processes[] = $pid;
            } else {
                $this->task($task);
                exit(0);
            }
        }
        while (count($this->processes) > 0) {
            $this->waitForProcess();
        }
    }

    protected function waitForProcess() {
        $pid = pcntl_wait($status);
        $this->processes = array_filter($this->processes, fn($p) => $p != $pid);
    }

    protected function task($payload) {
        // To be implemented in child classes
    }
}

class DirectScanner extends BugScanner {
    public function task($payload) {
        $method = $payload['method'];
        $host = $payload['host'];
        $port = $payload['port'];
        $url = $this->getUrl($host, $port);

        $this->log("Scanning $url with $method");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);  // No body for HEAD requests

        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $server = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        $this->log("Method: $method, Host: $host, Port: $port, Status: $status_code, Server: $server");
    }
}

class PingScanner extends BugScanner {
    public function task($payload) {
        $host = $payload['host'];
        $this->log("Pinging $host");

        $pingResult = shell_exec("ping -c 1 " . escapeshellarg($host));
        if (strpos($pingResult, '1 received') !== false) {
            $this->log("Host $host is reachable.");
        } else {
            $this->log("Host $host is unreachable.");
        }
    }
}

class UdpScanner extends BugScanner {
    public function task($payload) {
        $host = $payload['host'];
        $port = $payload['port'];
        $this->log("Scanning UDP port $port on $host");

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]);
        socket_sendto($socket, "", 0, 0, $host, $port);

        $read = socket_recvfrom($socket, $buf, 1024, 0, $host, $port);
        if ($read !== false) {
            $this->log("UDP port $port on $host is open.");
        } else {
            $this->log("UDP port $port on $host is closed or filtered.");
        }

        socket_close($socket);
    }
}

class SSLScanner extends BugScanner {
    public function task($payload) {
        $host = $payload['host'];
        $this->log("Checking SSL on $host");

        $context = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
        $client = stream_socket_client("ssl://$host:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

        if ($client) {
            $this->log("SSL certificate found for $host");
            fclose($client);
        } else {
            $this->log("SSL connection to $host failed: $errstr ($errno)");
        }
    }
}

class WebSocketScanner extends BugScanner {
    public function task($payload) {
        $host = $payload['host'];
        $this->log("Attempting WebSocket connection to $host");

        $url = "ws://$host";
        $headers = [
            "User-Agent: Mozilla/5.0",
            "Connection: Upgrade",
            "Upgrade: websocket"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (in_array($status_code, [101, 403, 426, 429, 500, 503])) {
            $this->log("WebSocket connection to $host succeeded with status $status_code");
        } else {
            $this->log("WebSocket connection to $host failed.");
        }
    }
}

function getArguments() {
    $options = getopt("f:c:m:M:p:P:o:T:");
    return [
        'filename' => $options['f'] ?? null,
        'cdir' => $options['c'] ?? null,
        'mode' => $options['m'] ?? 'direct',
        'method_list' => explode(',', $options['M'] ?? 'head'),
        'port_list' => explode(',', $options['p'] ?? '80'),
        'proxy' => $options['P'] ?? '',
        'output' => $options['o'] ?? null,
        'threads' => $options['T'] ?? 25,
    ];
}

function generateIpsFromCidr($cidr) {
    $hosts = [];
    $range = explode('/', $cidr);
    $ip = ip2long($range[0]);
    $mask = ~((1 << (32 - $range[1])) - 1);
    $start = ($ip & $mask) + 1;
    $end = ($ip | ~$mask) - 1;

    for ($i = $start; $i <= $end; $i++) {
        $hosts[] = long2ip($i);
    }
    return $hosts;
}

function main() {
    $arguments = getArguments();

    $methodList = $arguments['method_list'];
    $hostList = [];

    if (!empty($arguments['filename'])) {
        $hostList = file($arguments['filename'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    } elseif (!empty($arguments['cdir'])) {
        $hostList = generateIpsFromCidr($arguments['cdir']);
    }

    $portList = $arguments['port_list'];
    $mode = $arguments['mode'];
    $threads = $arguments['threads'];

    switch ($mode) {
        case 'direct':
            $scanner = new DirectScanner($threads);
            break;
        case 'ping':
            $scanner = new PingScanner($threads);
            break;
        case 'udp':
            $scanner = new UdpScanner($threads);
            break;
        case 'ssl':
            $scanner = new SSLScanner($threads);
            break;
        case 'ws':
            $scanner = new WebSocketScanner($threads);
            break;
        default:
            die("Invalid mode specified.\n");
    }

    foreach ($hostList as $host) {
        foreach ($portList as $port) {
            foreach ($methodList as $method) {
                $scanner->addTask(['method' => $method, 'host' => $host, 'port' => $port]);
            }
        }
    }

    $scanner->runTasks();
}

main();

?>
