import time
import sys

count = 0
while True:
  if count > 3:
    break
  #print('something.. ', count)
  #sys.stdout.write('wooot: %s' % count)
  print('something.. ', count)
  sys.stdout.flush()
  count += 1
  time.sleep(5)