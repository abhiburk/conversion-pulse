SELECT 'CREATE DATABASE pulse_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'pulse_testing')\gexec
