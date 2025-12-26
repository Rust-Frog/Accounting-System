#!/bin/bash

# Docker Management Script for Accounting System
# Usage: ./docker.sh [command] [environment]

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_ROOT"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

show_help() {
    echo "Accounting System - Docker Management"
    echo ""
    echo "Usage: ./docker.sh [command] [environment]"
    echo ""
    echo "Commands:"
    echo "  up         Start containers"
    echo "  down       Stop containers"
    echo "  restart    Restart containers"
    echo "  build      Build/rebuild containers"
    echo "  logs       Show container logs"
    echo "  shell      Access container shell"
    echo "  mysql      Access MySQL shell"
    echo "  composer   Run composer commands"
    echo "  test       Run tests"
    echo "  migrate    Run database migrations"
    echo "  seed       Seed database"
    echo "  fresh      Fresh database (drop & recreate)"
    echo "  cleanup-db Remove all data, keep schema"
    echo "  clean      Remove all containers and volumes"
    echo ""
    echo "Environments:"
    echo "  dev        Development environment (default)"
    echo "  prod       Production environment"
    echo ""
    echo "Examples:"
    echo "  ./docker.sh up dev"
    echo "  ./docker.sh logs dev"
    echo "  ./docker.sh shell dev"
    echo "  ./docker.sh composer 'install' dev"
    echo "  ./docker.sh migrate dev"
}

# Get environment
ENV=${2:-dev}

if [ "$ENV" != "dev" ] && [ "$ENV" != "prod" ]; then
    print_error "Invalid environment: $ENV. Use 'dev' or 'prod'"
    exit 1
fi

COMPOSE_FILE="docker/docker-compose.${ENV}.yml"

# Check if docker-compose file exists
if [ ! -f "$COMPOSE_FILE" ]; then
    print_error "Docker compose file not found: $COMPOSE_FILE"
    exit 1
fi

# Commands
case "$1" in
    up)
        print_info "Starting containers in $ENV mode..."
        docker-compose -f "$COMPOSE_FILE" up -d
        print_success "Containers started!"
        print_info "Application: http://localhost:8080"
        if [ "$ENV" == "dev" ]; then
            print_info "PHPMyAdmin: http://localhost:8081"
        fi
        ;;
    
    down)
        print_info "Stopping containers..."
        docker-compose -f "$COMPOSE_FILE" down
        print_success "Containers stopped!"
        ;;
    
    restart)
        print_info "Restarting containers..."
        docker-compose -f "$COMPOSE_FILE" restart
        print_success "Containers restarted!"
        ;;
    
    build)
        print_info "Building containers..."
        docker-compose -f "$COMPOSE_FILE" build --no-cache
        print_success "Build complete!"
        ;;
    
    logs)
        docker-compose -f "$COMPOSE_FILE" logs -f
        ;;
    
    shell)
        print_info "Accessing app container shell..."
        docker-compose -f "$COMPOSE_FILE" exec app sh
        ;;
    
    mysql)
        print_info "Accessing MySQL shell..."
        docker-compose -f "$COMPOSE_FILE" exec mysql mysql -u accounting_user -p accounting_system
        ;;
    
    composer)
        if [ -z "$2" ]; then
            print_error "Composer command required. Example: ./docker.sh composer 'install'"
            exit 1
        fi
        print_info "Running composer $2..."
        docker-compose -f "$COMPOSE_FILE" exec app composer $2
        ;;
    
    test)
        print_info "Running tests..."
        docker-compose -f "$COMPOSE_FILE" exec app composer test
        ;;
    
    migrate)
        print_info "Running database migrations..."
        docker-compose -f "$COMPOSE_FILE" exec app php migrate up
        print_success "Migrations completed!"
        ;;
    
    seed)
        print_info "Seeding database..."
        docker-compose -f "$COMPOSE_FILE" exec app php seed
        print_success "Seeding completed!"
        ;;
    
    fresh)
        print_info "Fresh database setup (drop all tables and recreate schema)..."
        docker-compose -f "$COMPOSE_FILE" exec -T mysql mysql -u accounting_user -paccounting_pass accounting_system < docker/mysql/00-fresh-schema.sql
        print_success "Database fresh setup completed!"
        ;;

    cleanup-db)
        print_info "Cleaning up database (removing all data, keeping schema)..."
        docker-compose -f "$COMPOSE_FILE" exec -T mysql mysql -u accounting_user -paccounting_pass accounting_system < docker/mysql/cleanup.sql
        print_success "Database cleanup completed! All data removed, schema intact."
        ;;
    
    clean)
        print_info "WARNING: This will remove all containers, volumes, and data!"
        read -p "Are you sure? (yes/no): " -r
        if [[ $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
            docker-compose -f "$COMPOSE_FILE" down -v
            print_success "Cleanup complete!"
        else
            print_info "Cleanup cancelled"
        fi
        ;;
    
    *)
        show_help
        ;;
esac
