import argparse
parser = argparse.ArgumentParser()
parser.parse_args()

import subprocess
import sys
import signal
import tempfile
import io
import time
import os

base = ['ruby', 'C:/Ruby200/bin/filewatcher']
c = ["/usr/bin/ruby", "/usr/local/bin/filewatcher", "."]
windows = ['ruby', 'C:/Ruby200/bin/filewatcher', os.getcwd()]
compiled = ['filewatcher.exe', os.getcwd()]
ls = ['ls']
tick = ['python', '%s/%s' % (os.path.dirname(os.path.abspath(__file__)), 'tick.py')]

def execute(command):
    print(command)
    popen = subprocess.Popen(command, stdout=subprocess.PIPE)
    lines_iterator = iter(popen.stdout.readline, b"")
    for line in lines_iterator:
        print(line) # yield line
        popen.stdout.flush()

execute(compiled)