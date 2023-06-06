# txt2jira - text to Jira - time tracking for the command line

![CI](https://github.com/klammbueddel/txt2jira/actions/workflows/test.yml/badge.svg)

Log your work in a simple text file and post it to Jira with a single command. 
Edit the file manually or use the provided commands to interact with the log file.

## Key benefits
* Logs your work locally, commits accumulated logs to Jira once you are done.
* Provides autocompletion for recently used issues and comments.
* Resolves summary of issues from Jira.
* Shows a summary of the logged time per day.
* Rounds time to frames of 5 minutes by default.
* Detects issue number in branch name or URL.
* Supports absolute (hh:mm) or relative time formats (1h / 30m).
* Supports alias to quickly log work on a specific issue.

## Log format

```text
<issue> as <alias>

<date>

<start time>
<issue | alias> <comment>
<end time>
```

## Getting started

Start your work day with the `log` command. It will add the current time to the log file rounded to 5 minute.

```bash
09:02 $ txt2jira log TEST-1 Do some work
Started TEST-1 Do some work at 09:00
```

The log file will look like this:

```text
04.12.2022

09:00
TEST-1 Do some work
```

Use the `list` command to see all entries in the log file. Notice that the summary was fetched from Jira.

```bash
09:02 $ txt2jira list
-------------------------------------------------- 04.12.22 -----------------------------------
* 09:00 - 09:02  TEST-1     As a user I want to eat choco cup cakes     2m      Do some work
----------------------------------------------------- 5m --------------------------------------
```

### Switching between tasks

It is easy to switch to another task by just starting it.

```bash
09:19 $ txt2jira log TEST-2 Do some other work
Stopped TEST-1 Do some work at 09:20
Started TEST-2 Do some other work at 09:20
```

```text
04.12.2022

09:00
TEST-1 Do some work
09:20
TEST-2 Do some other work
```

### Stop working

Running the `log` command while a log is active will stop the current log. Add optional `<time>` or `<duration>` for alternative end time.

```bash
09:29 $ txt2jira log
Stopped TEST-1 Do some work at 09:30
```

The end time is added to the log file.
```text
04.12.2022

09:00
TEST-1 Do some work
09:20
TEST-2 Do some other work
09:30
```

### Explore other useful features
* Use the `log` command without arguments to choose from the last recent tasks.
* Use the `log` command with `<issue>` or `<issue> <comments>` arguments to switch to task.
* Type `log 10m TEST-1` to add a task with a duration of 10 minutes.  
* Type `log 10:00 15m TEST-1` to add a task from 10:00 to 10:15.  
* Type `log -c` to continue from last end date. 
* Type `log 1h` to add a break of 1 hour if task is still running. 
* Use the `time` command to manipulate the current start / end time.
* Use the `comment` command to change the comment of the current task.
* Use the `comment -a` command to add comments to the current task.
* Use the `issue` command to change the issue of the current task.
* Use the `delete` command to remove a task from the log file.
* Use the `clear-cache` command to clear the cache.

### Commit logs to Jira
Once you are done with your work day, you can commit the logs to Jira with the `commit` command.

```bash
09:41 $ txt2jira commit
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

* PHP 8.1 or higher
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
alias ws='txt2jira log'
alias wl='txt2jira list'
alias wd='txt2jira delete'
alias wt='txt2jira time'
alias wi='txt2jira issue'
alias wc='txt2jira comment'
```

## Other features

### Aliases

Use aliases for recurring tasks.

```TEXT
TEST-1 as Coffee

14.10.2022

08:00
Coffee Clean machine
08:15
```

### Comment aggregation

```TEXT
TEST-1 as Coffee

04.12.2022

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

14.10.2022

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
