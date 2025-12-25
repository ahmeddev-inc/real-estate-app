-- إنشاء قاعدة بيانات عقار زين
CREATE DATABASE estate_db;

-- إنشاء أدوار إضافية
CREATE ROLE aaker_admin WITH LOGIN PASSWORD 'admin123';
CREATE ROLE aaker_app WITH LOGIN PASSWORD 'app123';

-- منح الصلاحيات
GRANT ALL PRIVILEGES ON DATABASE estate_db TO aaker_admin;
GRANT CONNECT ON DATABASE estate_db TO aaker_app;

-- الإتصال بقاعدة البيانات
\c estate_db;

-- إنشاء extension للـ UUID
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- إنشاء extension للبحث النصي
CREATE EXTENSION IF NOT EXISTS "unaccent";

-- تعليق توضيحي
COMMENT ON DATABASE estate_db IS 'قاعدة بيانات نظام عقار زين - Real Estate Management System';
