#!/bin/bash
set -e

# start dbus (needed by many desktop components)
if [ ! -d /var/run/dbus ]; then
  sudo mkdir -p /var/run/dbus
fi
sudo /etc/init.d/dbus start || true

# start xrdp session manager and xrdp
sudo /usr/sbin/xrdp-sesman
sudo /usr/sbin/xrdp -n

# keep container running
tail -f /dev/null
