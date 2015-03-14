<?php

require_once(dirname(__FILE__).'/../vendor/autoload.php');
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpProcess;
use League\CLImate\CLImate;
use Parse\ParseClient;

$config = require_once(dirname(__FILE__).'/config.php');

$climate = new CLImate;
$arguments = [
    'filter' => [
        'prefix'        => 'f',
        'longPrefix'    => 'filter',
        'description'   => 'Filter which filetype that will be monitored',
        'defaultValue'  => '*.php'
    ],
];
$climate->arguments->add($arguments);
try{
    $climate->arguments->parse();
}catch(Exception $e){
    $climate->out('you need this yo!');
    exit;
}

$monitor_path =  str_replace('\\', '/', getcwd());
// Include filter into the folder's path
// TODO: check if value is not directory then its ok.
$filters = explode(' ', $climate->arguments->get('filter'));
$monitor_path = sprintf('%s %s', $monitor_path, implode(' ', $filters));

$format = json_encode(['"filename"' => '"%FILENAME%"', '"event"' => '"%FSEVENT%"']);
$cmd = sprintf('filewatcher "%s" "echo %s"', $monitor_path, $format);
$process = new Process($cmd);
$process->start();
$climate->red()->out('MONITORING STARTED');

/* 
- Create monitoring session

*/
    
while($process->isRunning()){
    echo $process->getIncrementalOutput();
}