# Use Ubuntu as base image to support both PHP and Python
FROM ubuntu:22.04

# Prevent interactive prompts during installation
ARG DEBIAN_FRONTEND=noninteractive

# Install PHP, Python, and required packages
RUN apt-get update && apt-get install -y \
    php8.1 \
    php8.1-cli \
    php8.1-mysqli \
    php8.1-pdo \
    php8.1-mysql \
    php8.1-curl \
    php8.1-json \
    python3 \
    python3-pip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Create symbolic link for python
RUN ln -s /usr/bin/python3 /usr/bin/python

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install Python dependencies
RUN pip3 install -r requirements.txt

# Make start script executable
RUN chmod +x start.sh

# Expose the port that Render expects
EXPOSE $PORT

# Start both services using our custom script
CMD ["./start.sh"]
