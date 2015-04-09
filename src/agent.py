import argparse
import pdb
import os
import sys
import time
import getpass
import yaml
import random
import string
from watchdog.observers import Observer  
from watchdog.events import PatternMatchingEventHandler
from parse_rest.connection import register
from parse_rest.datatypes import Object
from parse_rest.query import QueryResourceDoesNotExist

class CodeMonHandler(PatternMatchingEventHandler):
    patterns = []
    ignore_patterns = []
    ignore_directories = False
    case_sensitive = False
    fileChanges = ''
    
    def __init__(self, args=None, session=None):
        self.patterns = args.filters
        self.ignore_patterns = ['*.git*']
        self.session = session
        self.args = args

    def process(self, event):
        """
        event.event_type 
            'modified' | 'created' | 'moved' | 'deleted'
        event.is_directory
            True | False
        event.src_path
            path/to/observed/file
        """
        # the file will be processed there
        # print(event.src_path, event.event_type)  # print now only for debug
        
        within_interval = self.within_interval(event.src_path) # check if file is within interval, if it is update existing record
        #self.fileChanges.filename = os.path.basename(event.src_path)
        self.fileChanges.filename = os.path.abspath(event.src_path)
        self.fileChanges.type = 'directory' if event.is_directory else 'file'
        self.fileChanges.parent = self.session.as_pointer
        self.fileChanges.event = event.event_type
        if event.event_type == 'modified' or (event.event_type == 'created' and event.is_directory == False):
            print('Saving %s file contents: %s' % (event.event_type, self.fileChanges.filename))
            with open(event.src_path, 'r') as contents:
                self.fileChanges.content = contents.read()
        else:
            self.fileChanges.content = ''
            
        self.fileChanges.save()
          
        
    def within_interval(self, src_path):
        fileChangesObj = Object.factory('FileChanges')
        try:
          fileChanges = fileChangesObj.Query.all().filter(parent = self.session.as_pointer,
                                   filename = os.path.basename(src_path),
                                   type = 'file').limit(1)
          #pdb.set_trace()
          fileChanges = fileChanges[0] if len(fileChanges) > 0 else False
        except QueryResourceDoesNotExist:
          fileChanges = False
          
        
        if fileChanges:
            current_time = time.gmtime()
            time_diff = (time.mktime(current_time) - time.mktime(fileChanges.updatedAt.timetuple()))
            if time_diff > self.args.interval_limit:
                print('Over interval limit')
                self.fileChanges = fileChangesObj()
                return False
            else:
                print('within interval')
                self.fileChanges = fileChanges
                
        else:
            self.fileChanges = fileChangesObj()
        
        return True
        

    def on_modified(self, event):
        self.process(event)

    def on_created(self, event):
        self.process(event)
        
if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Monitor current folder')
    parser.add_argument('-f', '--filter', dest='filters', nargs='*',
                        default=['*.py', '*.txt'], help='Filter which filetype that will be monitored, default is all')
    parser.add_argument('-i', '--interval', dest='interval_limit', default=120,
                        help='Interval between creating a new record for changes')
    parser.add_argument('-n', '--name', dest='monitor_name', 
                        default='%s - %s' % (getpass.getuser(), os.path.basename(os.getcwd())), help='This session\'s name')
    parser.add_argument('-d','--directory', dest='monitor_path', 
                        default='.', help='Which folder to monitor')
    args = parser.parse_args()
    
    f = open('%s/config.yaml' % os.path.abspath(os.path.dirname(sys.argv[0])))          
    config = yaml.safe_load(f)
    f.close()
    register(config['parse']['app_id'], config['parse']['rest_key'], master_key=None)
    
    # Create session
    monObj = Object.factory('MonSession')
    monObj = monObj()
    monObj.user = getpass.getuser()
    monObj.machineID = ''.join(random.choice(string.ascii_uppercase + string.digits + string.ascii_lowercase) for _ in range(32))
    monObj.directory = os.path.abspath(args.monitor_path)
    monObj.name = args.monitor_name
    monObj.save()
    print('Session created: %s' % monObj.objectId)
    
    observer = Observer()
    observer.schedule(CodeMonHandler(args=args,session=monObj), path=args.monitor_path, recursive=True)
    observer.start()

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        observer.stop()

    observer.join()
    