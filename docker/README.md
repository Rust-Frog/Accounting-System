# Docker Development Environment

## Services Running

All containers are now running and accessible:

- **Nginx**: http://localhost:8080 (Web server)
- **PHPMyAdmin**: http://localhost:8081 (Database management)
- **MySQL**: localhost:3306 (Database)
  - Database: accounting_system
  - User: accounting_user
  - Password: accounting_pass
  - Root Password: root_password
- **Redis**: localhost:6379 (Cache/Queue)
- **PHP-FPM**: Port 9000 (PHP application with Xdebug)

## Quick Commands

### Start containers
```bash
cd docker
docker compose -f docker-compose.dev.yml up -d
```

### Stop containers
```bash
cd docker
docker compose -f docker-compose.dev.yml down
```

### View logs
```bash
cd docker
docker compose -f docker-compose.dev.yml logs -f
```

### Execute commands in app container
```bash
cd docker
docker compose -f docker-compose.dev.yml exec app bash
docker compose -f docker-compose.dev.yml exec app composer install
docker compose -f docker-compose.dev.yml exec app php vendor/bin/phpunit
```

## Status
✅ Docker environment built successfully
✅ All containers running
✅ Composer dependencies installed
✅ Ready for development
