# txt2jira - text to Jira work log – work logging made easy and fun again

![CI](https://github.com/klammbueddel/txt2jira/actions/workflows/test.yml/badge.svg)

Log your work in a simple text file and post it to Jira with a single command. 
Edit the file manually or use the provided commands to interact with the log file.

## Getting started

Start your work day with the `start` command. It will add the current time to the log file rounded to 5 minute.

```bash
09:02 $ ./txt2jira start TEST-1 Do some work
Started TEST-1 Do some work at 09:00
```

The log file will look like this:

```text
04.12.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

09:00
TEST-1 Do some work
```

Use the `list` command to see all entries in the log file. Notice that the summary was fetched from Jira.

```bash
09:02 $ ./txt2jira list
-------------------------------------------------- 04.12.22 -----------------------------------
* 09:00 - 09:02  TEST-1     As a user I want to eat choco cup cakes     2m      Do some work
----------------------------------------------------- 5m --------------------------------------
```

### Switching between tasks

It is easy to switch to another task by just starting it.

```bash
09:19 $ ./txt2jira start TEST-2 Do some other work
Started TEST-2 Do some orther work at 09:20
Stopped TEST-1 Do some work at 09:20
```

```text
04.12.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

09:00
TEST-1 Do some work
09:20
TEST-2 Do some other work
```

### Stop working

Use the `stop` command when you are finished or need a break. Using the `start` command will also do the job.

```bash
09:29 $ ./txt2jira stop`
Stopped TEST-1 Do some work at 09:30
```

The end time is added to the log file.
```text
04.12.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

09:00
TEST-1 Do some work
09:20
TEST-2 Do some other work
09:30
```

### Explore other useful features
* Use the `start` command without arguments to select from the last recent tasks.
* Use the `edit` command to manipulate the current start / end time.
* Use the `comment` command to change or add comments to the current task.
* Use the `issue` command to change the issue of the current task.
* Use the `delete` command to remove a task from the log file.

### Commit logs to Jira
Once you are done with your work day, you can commit the logs to Jira with the `commit` command.

```bash
09:41 $ ./txt2jira commit
-------------------------------------------------- 04.12.22 -----------------------------------------------------
* 09:00 - 09:20  TEST-1     As a user I want to eat choco cup cakes              20m     Do some work
* 09:20 - 09:30  TEST-2     As an administrator I want to grant access to c...   10m     Do some other work
----------------------------------------------------- 30m -------------------------------------------------------

Commit to Jira? (y/n): y
Log 20m     TEST-1 Do some work ✓
Log 10m     TEST-2 Do some other work ✓
All done!
```

* Enjoy the end of the day without thinking about completing logs at the end of month.

### Requirements:

* PHP 8.1
* CURL

### Installation

```bash
git clone https://github.com/klammbueddel/txt2jira
cd txt2jira
composer install --no-dev
```

Setup configuration with `./txt2jira config`

Maybe you want to add the `txt2jira` command to your path.
```bash
sudo ln -s $(pwd)/txt2jira /usr/local/bin/txt2jira
```

Maybe you want to add some bash aliases to ease the usage even more.
```bash
alias w='txt2jira'
alias ws='txt2jira start'
alias wl='txt2jira list'
alias wd='txt2jira delete'
alias wc='txt2jira comment'
alias we='txt2jira edit'
alias wi='txt2jira issue'
```

## Other features

### Aliases

Use aliases for recurring tasks.

```TEXT
TEST-1 as Coffee

14.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
Coffee Clean machine
08:15
```

### Comment aggregation

```TEXT
TEST-1 as Coffee

04.12.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
Coffee Clean machine
08:15
Coffee Drink coffee
08:30
TEST-2 Talk to customer
08:45
Coffee Wash dishes
09:00
``` 

Will be aggregated to
```
45m TEST-1 Clean machine; Drink coffee; Wash dishes
15m TEST-2 Talk to customer
```

### Flag logged items

Once committed, log items will be flagged with an `x`.

```TEXT
TEST-1 as Coffee

14.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
Coffee Clean machine x
08:15
Coffee Drink coffee x
08:30
TEST-2 Talk to customer x
08:45
Coffee Wash dishes x
09:00
```

## License

MIT 2022 Christian Bartels
