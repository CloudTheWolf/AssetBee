# Server

To install dependencies:

<<<<<<< Updated upstream
AssetBee is a Simple Asset Management System designed to make managing you IT Assets easy.
=======
```bash
bun install
```
>>>>>>> Stashed changes

To run:

<<<<<<< Updated upstream
## Required Crons:
```cronexp
# Process queue every x mins
* * * * * php artisan queue:work --stop-when-empty >/dev/null 2>&1

# Run Cron Jobs

```
=======
```bash
bun run dist/db.js
```

This project was created using `bun init` in bun v1.2.4. [Bun](https://bun.sh) is a fast all-in-one JavaScript runtime.
>>>>>>> Stashed changes
