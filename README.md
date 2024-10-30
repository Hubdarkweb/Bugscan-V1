Here’s a user manual for using the PHP-based **BugScanner** tool to perform various types of network scans. This will guide you through setting up and running different scans (HTTP, Ping, UDP, SSL, WebSocket) on specified hosts and ports.

---

# BugScanner Tool - User Manual

This **BugScanner** tool is designed to perform several types of network scans on given hosts and ports. It supports scanning modes such as HTTP requests, ping tests, SSL/TLS checks, UDP port checks, and WebSocket connections.

---

## Table of Contents

1. **Installation Requirements**
2. **Getting Started**
3. **Usage Instructions**
4. **Scanning Modes**
5. **Examples**

---

## 1. Installation Requirements

Before using the BugScanner tool, ensure that your system meets the following requirements:

1. **PHP 7.4+** installed on your system.
2. **PCNTL extension** for parallel processing. (PCNTL is usually available on Unix-like operating systems.)
3. **CURL extension** for HTTP and WebSocket requests.
4. **Socket support** in PHP for UDP and WebSocket connections.

### Checking Requirements

To check if these requirements are met, you can use:
```bash
php -m | grep -E "pcntl|curl"
```

If `pcntl` or `curl` does not appear, you may need to install or enable them in your PHP configuration.

---

## 2. Getting Started

1. **Download the Script**: Save the `BugScanner` PHP script to your desired directory.
2. **Prepare Your Host List**: You can either provide a list of hosts via a file or use a CIDR notation to generate IP ranges for scanning.

### File Preparation

- Create a text file (e.g., `hosts.txt`) with a list of IPs or domain names, each on a new line:
  ```plaintext
  192.168.1.1
  192.168.1.2
  example.com
  ```

### Command-Line Arguments

Use the following command-line options to configure your scan:
- **`-f`** or **`--filename`**: Path to the file with hosts.
- **`-c`** or **`--cdir`**: CIDR notation (e.g., `192.168.1.0/24`) to generate IP addresses.
- **`-m`** or **`--mode`**: The scan mode. Choices are `direct`, `ping`, `udp`, `ssl`, or `ws`.
- **`-M`** or **`--method`**: HTTP method(s) for `direct` scan mode (e.g., `GET`, `HEAD`). Multiple methods should be comma-separated.
- **`-p`** or **`--port`**: Port number(s) to scan, separated by commas (e.g., `80,443`).
- **`-T`** or **`--threads`**: Number of threads (child processes) for parallel scanning.
- **`-o`** or **`--output`**: Output file name to save scan results.

---

## 3. Usage Instructions

Run the tool from the command line by specifying the appropriate options. The general syntax is:

```bash
php BugScanner.php [options]
```

---

## 4. Scanning Modes

### 1. **Direct HTTP/HTTPS Scan (`direct` mode)**

**Description**: Performs HTTP or HTTPS requests on specified hosts and ports to check server response codes, headers, and availability.

**Options**:
- **`-M`** (HTTP method): Specify HTTP methods such as `GET`, `POST`, `HEAD`, etc.
- **`-p`** (Port): Specify ports, typically `80` for HTTP and `443` for HTTPS.

**Example**:
```bash
php BugScanner.php -f hosts.txt -m direct -M GET,HEAD -p 80,443 -T 10
```

This example performs `GET` and `HEAD` requests on `hosts.txt` with ports `80` and `443` using 10 threads.

### 2. **Ping Scan (`ping` mode)**

**Description**: Sends ICMP ping requests to each host to check reachability.

**Options**:
- **`-T`** (Threads): Number of parallel threads for faster ping scanning.

**Example**:
```bash
php BugScanner.php -f hosts.txt -m ping -T 5
```

This command pings each host in `hosts.txt` with 5 threads.

### 3. **UDP Port Scan (`udp` mode)**

**Description**: Sends UDP packets to specified ports on each host to detect open or closed ports.

**Options**:
- **`-p`** (Port): Specify UDP ports to check.

**Example**:
```bash
php BugScanner.php -c 192.168.1.0/24 -m udp -p 53,123 -T 5
```

This scans UDP ports `53` (DNS) and `123` (NTP) on each IP in the `192.168.1.0/24` CIDR range with 5 threads.

### 4. **SSL/TLS Certificate Scan (`ssl` mode)**

**Description**: Checks if an SSL certificate exists and is valid for each host on port 443.

**Example**:
```bash
php BugScanner.php -f hosts.txt -m ssl -T 10
```

This command scans each host in `hosts.txt` to verify SSL/TLS certificates on port 443 using 10 threads.

### 5. **WebSocket Scan (`ws` mode)**

**Description**: Attempts a WebSocket connection to check if the WebSocket protocol is supported on a specified host.

**Example**:
```bash
php BugScanner.php -f hosts.txt -m ws -p 80,443 -T 5
```

This attempts WebSocket connections on ports 80 and 443 for each host in `hosts.txt`.

---

## 5. Examples

### Example 1: Basic HTTP Scan on Port 80

```bash
php BugScanner.php -f hosts.txt -m direct -M GET -p 80 -T 10
```
This scans each host in `hosts.txt` with an HTTP `GET` request on port 80, using 10 threads.

### Example 2: SSL Scan for Hosts in CIDR Range

```bash
php BugScanner.php -c 192.168.1.0/24 -m ssl -T 5
```
This checks SSL certificates for each host in the `192.168.1.0/24` range on port 443, using 5 threads.

### Example 3: UDP Scan on Ports 53 and 123 with CIDR Input

```bash
php BugScanner.php -c 10.0.0.0/24 -m udp -p 53,123 -T 5
```
This scans UDP ports 53 and 123 on the `10.0.0.0/24` CIDR range using 5 threads.

### Example 4: Ping Scan and Save Output

```bash
php BugScanner.php -f hosts.txt -m ping -o results.txt -T 10
```
This pings each host in `hosts.txt` using 10 threads and saves the results to `results.txt`.

### Example 5: WebSocket Scan on Ports 80 and 443

```bash
php BugScanner.php -f hosts.txt -m ws -p 80,443 -T 10
```
This attempts a WebSocket connection on ports 80 and 443 for each host in `hosts.txt` using 10 threads.

---

### Saving Results

To save the output to a file, use the `-o` option followed by the filename. For example:

```bash
php BugScanner.php -f hosts.txt -m direct -M GET -p 80 -T 10 -o output.txt
```

This command will save the HTTP GET scan results on port 80 to `output.txt`.

---

This user manual provides all necessary steps to configure and run different scan modes using the **BugScanner** PHP script. Each example shows common use cases, and the CLI options allow flexibility for custom scans.
