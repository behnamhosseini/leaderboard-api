#!/bin/bash

echo "=== Setting up Leaderboard Service ==="

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "Error: Composer is not installed. Please install Composer first."
    exit 1
fi

# Install dependencies
echo "Installing PHP dependencies..."
composer install

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
    echo "Please update .env with your database and Redis credentials"
fi

# Check if Docker is available
if command -v docker &> /dev/null && command -v docker-compose &> /dev/null; then
    echo ""
    read -p "Do you want to start Docker services? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "Starting Docker services..."
        docker-compose up -d
        
        echo "Waiting for services to be ready..."
        sleep 5
        
        echo "Running database migrations..."
        docker-compose exec -T mysql mysql -uroot -proot leaderboard_db < database/schema.sql
        
        echo "Setup complete! Services are running."
        echo "API is available at: http://localhost:8000"
    fi
else
    echo "Docker not found. Please set up MySQL and Redis manually."
    echo "Then run: mysql -uroot -p < database/schema.sql"
fi

echo ""
echo "=== Setup Complete ==="

