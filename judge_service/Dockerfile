# Judge microservice Docker image
FROM python:3.10-slim

# Install Docker CLI so the service can spawn sibling containers
RUN apt-get update && apt-get install -y \
    docker.io \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py runner.py ./

EXPOSE 5000

CMD ["python", "app.py"]
