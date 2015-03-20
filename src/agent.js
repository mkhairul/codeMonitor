#!/usr/bin/env node
'use strict';
var child_process = require('child_process');
var sprintf = require('sprintf-js').sprintf;
var ArgumentParser = require('argparse').ArgumentParser;
var parser = new ArgumentParser({
  version: '0.0.1',
  addHelp: true,
  description: 'monitor changes to this folder'
});

parser.addArgument(
  ['-f', '--filter'],
  {
    help: 'Filter which filetype that will be monitored',
    defaultValue: '*'
  }
);
parser.addArgument(
  ['-i', '--interval'],
  {
    help: 'Interval between creating new record for changes',
    defaultValue: '120'
  }
);
parser.addArgument(
  ['-n', '--name'],
  {
    help: 'Session\'s name',
    defaultValue: 'lorem ipsum'
  }
);

var args = parser.parseArgs();
var monitor_path = __dirname.replace(/\\/g, '/');
var filters = args.filter.split(' ');
var monitor_pattern = sprintf('%s/**/%s', monitor_path, filters.join('||'));

var ruby_path = '';
var filewatcher_path = '';
var format = JSON.stringify({'"filename"': '"%FILENAME%"', '"event"': '"%FSEVENTS%"'});
var cmd = sprintf('filewatcher "%s" "echo %s"', monitor_pattern, format);
var cmd = sprintf('%s %s %s', ruby_path, filewatcher_path, __dirname);

child_process.exec(cmd, function(err, stdout, stderr){
  if(err){
    console.log('child process failed with error code: ' + err.code);
  }
  console.log(stdout);
  console.log(err);
  console.log(stderr);
});
