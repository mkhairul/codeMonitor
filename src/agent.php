<?php
require_once(dirname(__FILE__).'/../vendor/autoload.php');
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpProcess;
use League\CLImate\CLImate;
use Parse\ParseClient;
use Parse\ParseObject;
use Parse\ParseException;
use Parse\ParseQuery;

$config = require_once(dirname(__FILE__).'/config.php');

$climate = new CLImate;
$arguments = [
    'filter' => [
        'prefix'        => 'f',
        'longPrefix'    => 'filter',
        'description'   => 'Filter which filetype that will be monitored',
        'defaultValue'  => '*'
    ],
    'interval' => [
        'prefix'        => 'i',
        'longPrefix'    => 'interval',
        'description'   => 'Interval between creating new record for changes',
        'defaultValue'  => '120' // seconds (2 minutes by default)
    ],
    'name'  => [
        'prefix'        => 'n',
        'longPrefix'    => 'name',
        'description'   => 'This session\'s name',
        'defaultValue'  => 'lorem ipsum'
    ],
    'directory' => [
        'prefix'        => 'd',
        'longPrefix'    => 'directory',
        'description'   => 'Which folder to monitor',
        'defaultValue'  => '*'
    ]
];
$climate->arguments->add($arguments);
try{
    $climate->arguments->parse();
}catch(Exception $e){
    $climate->out('you need this yo!');
    exit;
}

$interval_limit = $climate->arguments->get('interval');
$monitor_path =  str_replace('\\', '/', $climate->arguments->get('directory'));
// Include filter into the folder's path
// TODO: check if the value is not a directory
$filters = explode(' ', $climate->arguments->get('filter'));
$monitor_pattern = sprintf('%s/**/%s', $monitor_path, implode('||', $filters));

$format = json_encode(['"filename"' => '"%FILENAME%"', '"event"' => '"%FSEVENT%"']);
$cmd = sprintf('filewatcher "%s" "echo %s"', $monitor_pattern, $format);
echo $cmd;
$process = new Process($cmd);
$process->start();
$climate->red()->out('MONITORING STARTED');

ParseClient::initialize($config['parse']['app_id'], 
                        $config['parse']['rest_key'], 
                        $config['parse']['master_key']);

if(function_exists('posix_getpwuid'))
{
  $processUser = posix_getpwuid(posix_geteuid());
  $user = $processUser['name'];
}
else
{
  $user = getenv('username');
}

// Create an identifier for this machine
$id_path = dirname(__FILE__).'/.codemon';
$machineID = '';
if(file_exists($id_path))
{
  $machineID = file_get_contents($id_path);
}

if(!file_exists($id_path) or !$machineID)
{
  $factory = new RandomLib\Factory;
  $generator = $factory->getMediumStrengthGenerator();
  $machineID = $generator->generateString(32);
  file_put_contents($id_path, $machineID); 
}
$climate->out('machineID: ' . $machineID);

$monObj = ParseObject::create('MonSession');
$monObj->set('user', $user);
$monObj->set('machineID', $machineID);
$monObj->set('directory', $monitor_path);
$monObj->set('name', $climate->arguments->get('name'));
echo $monObj->save();
$mon_session_id = $monObj->getObjectId();
$climate->out('session created: ' . $mon_session_id);
    
while($process->isRunning()){
    if($output = $process->getIncrementalOutput())
    {
        $json = json_decode($output,true);
        if(!$json){ continue; }
        
        $filetype = filetype($json['filename']);
        
        // Find if there are similar changes saved
        $query = new ParseQuery('FileChanges');
        $query->equalTo('parent', $monObj);
        $query->equalTo('filename', $json['filename']);
        $query->equalTo('type', $filetype);
        $query->descending('updatedAt');
        $results = $query->find();
        
        $flag_new = 1;
        if(count($results) > 0)
        {
            $file_changes = $results[0];
            // Compare the duration
            $updatedAt = $file_changes->getUpdatedAt();
            $now = new DateTime('now');
            $interval = $now->diff($updatedAt);
            $minutes = $interval->format('%i');
            $seconds = $minutes * 60;
            $climate->out(sprintf('%s minutes ago updated', $minutes));
            if($seconds < $interval_limit)
            {
                $climate->out('Still within interval limit');
                $flag_new = 0;
            }
            else
            {
                $flag_new = 1;
            }
        }
       
        if($flag_new)
        {
            $climate->out('creating new record for changes');
            $file_changes = new ParseObject('FileChanges');
            $file_changes->set('filename',$json['filename']);
            $file_changes->set('type', $filetype);
            $file_changes->set('parent', $monObj);
            $climate->out('changes saved');
        }
        else
        {
            $climate->out('updating record for changes');
        }
        
        $file_changes->set('event',$json['event']);
        $file_changes->set('content', ($filetype === 'file' && $json['event'] != 'deleted') ? file_get_contents($json['filename']):'');
        $response = $file_changes->save();
        echo $output;
        echo $response;
    }
}