# Remote Backup Craft CMS Plugin

![Header image for plugin](https://craft-plugins-cdn.timmyomahony.com/website/remote-backup/remote-backup-plugin-header.png)

📓 [**Documentation**](https://craft-plugins.timmyomahony.com/remote-backup?utm_source=github) | 💳 [**Purchase**](https://plugins.craftcms.com/remote-backup?craft4) | 🤷🏻‍♂️ [**Get help**](https://craft-plugins.timmyomahony.com/remote-backup/docs/get-help)

Remote Backup is a Craft CMS plugin that allows you to automaticaly backup your database and volumes to remote cloud destinations like AWS S3, Digital Ocean, Backblaze and more, giving you peace of mind when making sites updates, content changes or adding new features.

It provides a useful interface for manually backing up your data via the Craft CMS Control Panel utilites section:

![Craft Remote Backup Overview](https://craft-plugins-cdn.timmyomahony.com/website/remote-backup/utilities-screenshot.jpg)

Remote Backup also lets you create backups with custom CLI commands, for example:

```bash
./craft remote-backup/database/create
```

Together with [cron](https://en.wikipedia.org/wiki/Cron) these commands can be used to take totally automated backups.

## Features

- **Multiple cloud providers**: remote backup supports numerous cloud providers including AWS and Backblaze.
- **Background queue**: use the Craft queue to avoid hanging around for backups to complete.
- **Supports large files**: backup large multi-GB volumes and databases to remote destinations.
- **CLI commands**: automate backups using the CLI commands and cron.
- **Prunes old backups**: automatically prune old backups so you never run out of space.
- **Remote volumes**: backup remote volumes to other remote locations for peace of mind (i.e. S3 to Backblaze)

## Documentation

See [the full documentation website](https://craft-plugins.timmyomahony.com/remote-backup) for details on how to get started with the plugin.
