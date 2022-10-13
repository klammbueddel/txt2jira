# txt2jira - text to Jira work log – work logging made easy and fun again


### Requirements:

* PHP 8.1
* CURL

### To install dependencies run

`composer install`

### To setup configuration run

`./txt2jira init`

### To show preview and commit logs run

`./txt2jira commit`

### Workflow

* Write your logs as you do your work.
* Commit once at the end of your work day.
* Enjoy the end of the day without thinking about completing logs at the end of month.

## Features

### Aliases

Use alias for recurring tasks.

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

14.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

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
45m TEST-1 Clean machine, Drink coffee, Wash dishes
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

### Preview with time aggregation

```
------------------------------------ 14.10.22 ------------------------------------
* Coffee     45m     Clean machine, Drink coffee, Wash dishes
* TEST-2     15m     Talk to customer
-------------------------------------- 1h ----------------------------------------
```


### Syntax check

```TEXT
TEST-1 as Coffee

Ups, I can not handle this text

14.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
Coffee Only one item is allowed per time slot!
Coffee Clean machine
08:15

What about this one?
```

The preview will show the diff that will be applied on commit.
```
@@ -1,12 +1,8 @@
TEST-1 as Coffee
-
-Ups, I can not handle this text

14.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
-Coffee Only one item is allowed per time slot!
Coffee Clean machine
08:15

-What about this one?
```

### Automatic commit

Once confirmed, all open logs will be posted to Jira.

```
Commit ? (y/n):
```

### Simple to use
Use this as a template for your work day

```TEXT
14.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
08:15
08:30
08:45
09:00
09:15
09:30
09:45
10:00
10:15
10:30
10:45
11:00
11:15
11:30
11:45
12:00
12:15
12:30
12:45
13:00
13:15
13:30
13:45
14:00
14:15
14:30
14:45
15:00
15:15
15:30
15:45
16:00
16:15
16:30
16:45
17:00
17:15
17:30
17:45
18:00
18:15
18:30
18:45
19:00
19:15
19:30
19:45
20:00

```

## License

MIT 2022 Christian Bartels
