FROM node:22.22-alpine AS builder
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund --ignore-scripts

COPY . .
RUN npx quasar prepare && npm run build

FROM node:22.22-alpine
WORKDIR /app

ENV NODE_ENV=production \
    DIST_DIR=/app/dist \
    PORT=3000

COPY --from=builder /app/dist/spa ./dist
COPY server.mjs ./

USER node
EXPOSE 3000
CMD ["node", "server.mjs"]
