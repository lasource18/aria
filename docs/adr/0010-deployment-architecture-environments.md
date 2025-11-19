# ADR-0010: Deployment Architecture and Environments

**Status**: Accepted
**Date**: 2025-11-19
**Deciders**: Architect Agent
**Tags**: [architecture, infra, deployment, devops]

## Context and Problem Statement

Aria requires regional deployment near Abidjan or EU-West for <200ms P95 latency. We need managed services to minimize ops burden for MVP team.

**Referenced sections**: DESIGN.md Section 15 (Deployment & Environments)

## Decision Outcome

**Platform**: Managed Kubernetes (GKE/EKS) OR Fly.io (simpler alternative for MVP)

### Recommended Stack (MVP)
- **Compute**: Fly.io (2x instances, EU-West region) OR DigitalOcean App Platform
- **Database**: DigitalOcean Managed PostgreSQL 16 (4 vCPU, 16GB RAM, Frankfurt/Amsterdam region)
- **Cache/Queue**: Upstash Redis (EU region, serverless pricing)
- **Object Storage**: Cloudflare R2 (S3-compatible, free egress)
- **CDN**: Cloudflare Pages for Next.js site + CDN for assets
- **Monitoring**: Sentry (errors), Prometheus + Grafana (metrics)

### Environment Separation
- **Dev**: Local Docker Compose (PostgreSQL + Redis + MinIO)
- **Staging**: Fly.io staging app + separate DB instance
- **Production**: Fly.io production app + HA PostgreSQL + Redis cluster

### CI/CD Pipeline (GitHub Actions)

```yaml
# .github/workflows/deploy-api.yml
name: Deploy API

on:
  push:
    branches: [main]
    paths: ['apps/api/**']

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: superfly/flyctl-actions/setup-flyctl@master

      - name: Run migrations
        run: |
          cd apps/api
          php artisan migrate --force

      - name: Deploy to Fly.io
        run: flyctl deploy --remote-only
        env:
          FLY_API_TOKEN: ${{ secrets.FLY_API_TOKEN }}
```

### Docker Configuration

```dockerfile
# infra/docker/api.Dockerfile
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Install dependencies
RUN apk add --no-cache postgresql-dev libpq-dev && \
    docker-php-ext-install pdo pdo_pgsql

# Copy Laravel app
COPY apps/api /var/www/html

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader

CMD ["php-fpm"]
```

## References
- DESIGN.md Section 15: Deployment & Environments
- External: [Fly.io](https://fly.io/), [DigitalOcean Managed DB](https://www.digitalocean.com/products/managed-databases)
