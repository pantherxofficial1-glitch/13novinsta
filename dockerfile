FROM php:8.2-alpine
COPY . .
CMD ["php", "-S", "0.0.0.0:10000", "bot.php"]   ← ✅ CORRECT
