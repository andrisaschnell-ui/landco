# Use Ubuntu as the base image
FROM ubuntu:22.04

# Set timezone to Africa/Maputo (Mozambique) and prevent interactive prompts
ENV TZ=Africa/Maputo
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Suppress interactive prompts and install desktop environment and basic tools
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && \
    apt-get install -y ubuntu-desktop xrdp wget git python3 python3-pip && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Create a user for desktop login
RUN useradd -m -s /bin/bash claudecode && echo 'claudecode:claudecode' | chpasswd && adduser claudecode sudo

# Set up XRDP for remote desktop access
EXPOSE 3389

# Set working directory
WORKDIR /home/claudecode

# Switch to the new user
USER claudecode

    # Clone Claude Code from official source (insert your GitHub token below)
    # Example: RUN git clone https://<YOUR_TOKEN>@github.com/anthropic/claude-code.git
    RUN git clone https://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@github.com/anthropic/claude-code.git


# Install Claude Code dependencies (example: Python)
WORKDIR /home/claudecode/claude-code
RUN pip3 install -r requirements.txt || true

# Start desktop session (XRDP)
CMD ["/usr/sbin/xrdp", "-n"]
