FROM php:8.4-cli

ARG TARGETPLATFORM

RUN apt-get update -y && apt-get install -y \
    git \
    curl \
    wget \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    gpg

RUN install -dm 755 /etc/apt/keyrings
RUN wget -qO - https://mise.jdx.dev/gpg-key.pub | gpg --dearmor | tee /etc/apt/keyrings/mise-archive-keyring.gpg 1> /dev/null
RUN if [ "$TARGETPLATFORM" = "linux/amd64" ]; then ARCHITECTURE=amd64; elif [ "$TARGETPLATFORM" = "linux/arm/v7" ]; then ARCHITECTURE=arm; elif [ "$TARGETPLATFORM" = "linux/arm64" ]; then ARCHITECTURE=arm64; else ARCHITECTURE=amd64; fi \
    && echo "deb [signed-by=/etc/apt/keyrings/mise-archive-keyring.gpg arch=${ARCHITECTURE}] https://mise.jdx.dev/deb stable main" | tee /etc/apt/sources.list.d/mise.list
RUN apt-get update -y
RUN apt-get install -y mise
RUN echo 'eval "$(/usr/bin/env mise activate bash)"' >> ~/.bashrc

# Clean up to reduce image size
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    mbstring \
    pcntl \
    bcmath \
    intl \
    zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /source

RUN mkdir -p ~/.local/state/mise/trusted-configs
RUN ln -s /source ~/.local/state/mise/trusted-configs/
RUN echo 'eval "$(/usr/bin/env mise activate zsh)"' >> ~/.zshrc

# Pre-install Node.js via mise to avoid downloading on every container run
# Copy mise.toml and install tools during build
COPY mise.toml /tmp/mise.toml
RUN cd /tmp && mise trust && mise install && rm -f mise.toml
