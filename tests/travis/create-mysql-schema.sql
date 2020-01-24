CREATE SCHEMA doctrine_tests;
CREATE SCHEMA test_create_database;
CREATE SCHEMA test_drop_database;

GRANT ALL PRIVILEGES ON doctrine_tests.* to travis@'%';
GRANT ALL PRIVILEGES ON test_create_database.* to travis@'%';
GRANT ALL PRIVILEGES ON test_drop_database.* to travis@'%';
