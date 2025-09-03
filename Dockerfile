# Use official PHP image
FROM php:8.1-cli

# Set working directory
WORKDIR /app

# Copy all project files
COPY . /app

# Expose port Render expects
EXPOSE 10000

# Start PHP server
CMD ["php", "-S", "0.0.0.0:10000", "-t", "/app"]
