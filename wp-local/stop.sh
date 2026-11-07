#!/usr/bin/env bash
set -e
systemctl --user stop parsian-wp.service
systemctl --user stop parsian-mysql.service
echo "stopped"
