#!/usr/bin/env bash
set -e
systemctl --user start parsian-mysql.service
systemctl --user start parsian-wp.service
echo "WordPress: http://127.0.0.1:8081"
echo "Admin:     http://127.0.0.1:8081/wp-admin/"
