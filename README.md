# IoT Alter Manager

This is a Symfony command-line tool that can be used to set up alerts (sending
both emails and text messages) based on the last time a specific gateway or
device was active (sent data).

For instance, if a device that regularly sends data stops communicating, this
tool will detect the inactivity. Once the absence of data is noticed, it will
automatically send out notifications to inform you. This can be particularly
useful for monitoring the status of critical devices and ensuring they are
functioning properly. It helps in quickly identifying and addressing potential
issues, thereby minimizing downtime and maintaining smooth operations.

## Overview

This application communicates with [OS² IoT](https://www.os2.eu/os2iot) API
looking up gateways, applications, and connected devices. The gateways and
applications are filtered based on their status (default: in-operation and
project).

This project do **not** have a user interface, but uses `tags` on
gateways and `metadata` on devices to detect whom to alter and when not to send
alters. This decision has been taken to keep this application relative simple
and to have IoT configuration in only one place (inside OS² IoT).

![Relationship between services](./assets/AlertManager.png)

The diagram above outlines the different services and how they are related in
this application.

The required data consumed from the IoT API are converted into value objects, to
ensure type safety and stability. This also ensures that data anomalies are
detected and logged.

The applications use Symfony mailer to send mails and
SMS2Go's [api](https://pushapi.ecmr.biz/docs/index.html?url=/swagger/v1/swagger.json)
gateway to send SMS's. Another gateway could, for example, be implemented using
the
`SmsClientInterface` and overriden the injection in `service.yml`

## Metrics

This application exposes metrics, which can be used for observability in
Prometheus using Grafana. The metrics also contain information about exceptions
thrown during execution.

The metrics are exposed at `/metrics` (it is possible to reconfigure this path)
in routing.

## Logs

The applications also output basic log information and errors to standard out
and error, which can be sent to [Loki](https://grafana.com/oss/loki/) when
running docker.

## Configurations

The application's configuration variables can be seen in `.env` and overriden
using `.env.local`.
See [Overriding Environment Values via .env.local](https://symfony.com/doc/current/configuration.html#overriding-environment-values-via-env-local)
in Symfony's documentation.

In the next section, we _highlight_ the configurations that you should or may
need to change to get the alter manager working properly.

### API access configuration

To access the IoT API and the SMS gateway, the follow settings, need to be
obtained.

* **API_CLIENT_KEY** (API key for the OS² IoT API)
* **SMS_GATEWAY_ID** (Identifier used at sms2go)
* **SMS_GATEWAY_TOKEN** (Token to access the sms gateway)
* **SMS_DRY_RUN** (If set to true SMS will not be sent)

### Status filter

This is used when commands are executed with `--filter-status` options to filter
out applications and devices that do have one of the status configurations in
this comma-separated list of statuses.

Value available are: `NONE`, `IN-OPERATION`, `PROTOTYPE`, `PROJECT` and `OTHER`

* **ALTER_STATUSES** (default: `IN-OPERATION,PROJECT`)

### Gateway alter configuration

The contact phone and e-mail are not required information on gateways, so if not
set these to variables, values will be used as fallback.

* **ALERT_GATEWAY_FALLBACK_MAIL**
* **ALERT_GATEWAY_FALLBACK_PHONE**

### Device alter configuration

To add contact information to devices, this application uses metadata on the
device. As metadata is free-text key/value pairs, one can use the configuration
below to define which keys should be used for the different contact data.

* **ALERT_DEVICE_METADATA_MAIL_FIELD** (default: `email`)
* **ALTER_DEVICE_METADATA_PHONE_FIELD** (default: `phone`)

Also, you can change the time from last seen (which is used to trigger an alter)
one each device using a metadata field. If not given, the fallback is used.

This is always given in seconds.

* **ALERT_DEVICE_FALLBACK_LIMIT** (default: `86400` - 24 hours)
* **ALTER_DEVICE_METADATA_THRESHOLD_FIELD** (default: `notification_threshold`)

If contact data is not found in the metadata configuration from above, these
variables below can be used to define fallbacks.

* **ALERT_DEVICE_FALLBACK_MAIL**
* **ALERT_DEVICE_FALLBACK_PHONE**

### Silence alters

This application does not have any state, so it will keep sending you the same
alter every time it is executed until the problem is resolved. There are two
ways to silence or acknowledge an alter:

1) Change its status to one not in `ALTER_STATUSES`.
2) Set a tag on a gateway or on as metadata on devices with an until date.

* **ALTER_GATEWAY_SILENCED_TAG** (default: `silenced_until`)
* **ALTER_DEVICE_METADATA_SILENCED_FIELD** (default: `silenced_until`)
* **ALTER_SILENCED_TIME_FORMAT** (default: `d-m-y\TH:i:s` eg. 18-09-24T16:00:00)

### Templates (mail and SMS)

All mails and SMSs content are formatted using twig templates. One way to change
the wording would be to copy the template (`/app/templates` in the phpfpm
container) folder and change the templates and then override the template by
mapping them into the container.

### Fallback order

This is the order of contact information fallback order.

**Gateways**:

* Command override
* Gateways contact information
* Gateway fallback mail (.env)

**Devices**:

* Command override
* Device metadata field
* Application contact mail
* Device fallback mail (.env)

## Commands

If using Docker, they are executed in the `phpfpm` container by executing
`bin/console`. All commands have `--help` option which will output text
explaining all the options and what they are used for.

For example, list all application filtered on the configured statuses:

```shell
docker composer exec phpfpm bin/console app:api:applications --filter-status
```

The main command for the application is the `checks alter` command, that runs
the alter manger service. This command has a large number of options to change
its behavior, so use `--help` to see them all.

Here are three examples that should cover the basic usage. The first checks all
gateways filtered base on configured status and disabling notification via SMS.

```shell
docker composer exec phpfpm bin/console app:alert:checks --only-gateways --filter-status --no-sms
```

The next command checks applications and thereby all devices found in the
applications.

```shell
docker composer exec phpfpm bin/console app:alert:checks --only-applications --filter-status
```

This command executes all tests and covers both gateways, applications (and
thereby devices).

```shell
docker composer exec phpfpm bin/console app:alert:checks --all --filter-status
```

### API consumption test commands

Collection of commands to test and see information extracted from the IoT SPI.

* app:api:application (Get a single application from API server)
* app:api:applications (Get applications from API server)
* app:api:device (Get device from API server)
* app:api:gateways (Get gateways from API server)

### Mail/Sms test commands

Two commands to test mails and SMS intergration.

* app:mail:test (Send test e-mail)
* app:sms:test (Send test SMS)
