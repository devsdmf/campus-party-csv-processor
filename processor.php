<?php

// checking if the queue id was provided
if (!isset($argv[1])) die('You must pass the queue id as argument to the script');

$pid = pcntl_fork();

if ($pid == -1) {
    die('could not fork');
} else if ($pid) {
    pcntl_wait($status, WNOHANG);
    exit(0);
    
} else {
    // here start the child
    posix_setsid();
    posix_setuid(501);
    posix_setgid(20);
}

// getting child pid 
$pid = posix_getpid();

// setups the data directory
define('DATA_DIR',realpath(__DIR__ . '/data'));

// getting argument
$queueId = intval($argv[1]);

// initializing the database connection
$db = new PDO('mysql:host=localhost;dbname=csv_processor','root',null);

// getting the queued process from the database
$stmt = $db->query("SELECT * FROM `queue` WHERE `id`=$queueId");
$process = $stmt->fetch(PDO::FETCH_ASSOC);

// checking if the process exists
if (!$process) die('The specified queue id was not found');

$stmt = $db->prepare('UPDATE `queue` SET `status`=?,`pid`=? WHERE `id`=?');
$stmt->execute(['running',$pid,$process['id']]);

// opening the file
$handler = fopen(DATA_DIR . '/' . $process['file'],'r');

// iterating over the files
while (!feof($handler)) {
    // fetching the row from the CSV file
    $data = fgetcsv($handler);

    // preparing the statement
    $stmt = $db->prepare('INSERT INTO `ceps` (`cep`,`street`,`neighborhood`,`city`,`state`) VALUES (?,?,?,?,?)');

    // inserting
    $stmt->execute([$data[0],$data[1],$data[2],$data[3],$data[5]]);
}

// updating the status
$stmt = $db->prepare('UPDATE `queue` SET `status`=?,`finished_at`=NOW() WHERE `id`=?');
$stmt->execute(['finished',$process['id']]);