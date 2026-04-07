#!/bin/sh
set -e

# Xóa cache
php artisan config:clear
php artisan cache:clear

# Xóa sạch bảng cũ và tạo lại mới hoàn toàn (Tránh lỗi Duplicate)
php artisan migrate:fresh --force

# Khởi động Apache
exec apache2-foreground