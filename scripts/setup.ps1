# PowerShell setup script for Windows

Write-Host "=== Setting up Leaderboard Service ===" -ForegroundColor Green

# Check if composer is installed
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    Write-Host "Error: Composer is not installed. Please install Composer first." -ForegroundColor Red
    exit 1
}

# Install dependencies
Write-Host "Installing PHP dependencies..." -ForegroundColor Yellow
composer install

# Copy environment file if it doesn't exist
if (-not (Test-Path .env)) {
    Write-Host "Creating .env file from .env.example..." -ForegroundColor Yellow
    Copy-Item .env.example .env
    Write-Host "Please update .env with your database and Redis credentials" -ForegroundColor Yellow
}

# Check if Docker is available
if (Get-Command docker -ErrorAction SilentlyContinue) {
    $response = Read-Host "Do you want to start Docker services? (y/n)"
    if ($response -eq 'y' -or $response -eq 'Y') {
        Write-Host "Starting Docker services..." -ForegroundColor Yellow
        docker-compose up -d
        
        Write-Host "Waiting for services to be ready..." -ForegroundColor Yellow
        Start-Sleep -Seconds 5
        
        Write-Host "Running database migrations..." -ForegroundColor Yellow
        Get-Content database/schema.sql | docker-compose exec -T mysql mysql -uroot -proot leaderboard_db
        
        Write-Host "Setup complete! Services are running." -ForegroundColor Green
        Write-Host "API is available at: http://localhost:8000" -ForegroundColor Green
    }
} else {
    Write-Host "Docker not found. Please set up MySQL and Redis manually." -ForegroundColor Yellow
    Write-Host "Then run: mysql -uroot -p < database/schema.sql" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=== Setup Complete ===" -ForegroundColor Green

