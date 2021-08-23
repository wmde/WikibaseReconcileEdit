This provides a quick and automated way to configure a test system for a 'WikibaseReconcileEdit' enabled Wikibase instance

### Prerequisites

We use [ansible](https://docs.ansible.com/ansible/latest/index.html) to manipulate remote servers via SSH - essentially automating what you'd otherwise do "by hand" in a step-by-step approach. Make sure to have version >=2.8, e.g. by installing via `pip`:
```
$ pip install ansible

$ ansible --version
ansible 2.9.6
```

You need to be in possession of an SSH private key for which there is an associated user that is authorized to perform the operations.

### Inventory

The file `inventory.yml` contains a single host, which is the target for the test system setup:
 * `wikibase-reconcile-testing.wmcloud.org` - the project's official cloud VPS test instance

### Setup

Set up your VPS instance on https://horizon.wikimedia.org and a web proxy to reach it from the internet, then:

```sh
$ cd extensions/WikibaseReconcileEdit/infrastructure
$ ansible-galaxy install -r requirements.yml
```
#### Deploy to test system

```sh
$ ansible-playbook setup.yml --limit <hostname>.wikidata-dev.eqiad1.wikimedia.cloud
```

#### Deploy to virtual machine

Add this in `/etc/hosts/`
```sh
192.168.100.42 wikibase-reconcile.vm
```

Add this to your `~/.ssh/config`

```sh
Host wikibase-reconcile.vm
  User vagrant
  IdentityFile /<PATH_TO_WIKIBASE_RECONCILE_EDIT>/infrastructure/vagrant/.vagrant/machines/default/virtualbox/private_key
```

Start the virtual machine by

```sh
$ cd infrastructure/vagrant/
$ vagrant up
```

Deploy to the virtual machine

```sh
$ ansible-playbook setup.yml --limit wikibase-reconcile.vm
```

Once the setup process has completed, you can access the newly installed Wikibase test system via web proxy, e.g. `https://wikibase-reconcile-testing.wmcloud.org/`.

Anonymous editing and user registration are disabled on the wiki – use [createAndPromote.php](https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:CreateAndPromote.php) to create user accounts:

```sh
$ ssh <hostname>.wikidata-dev.eqiad1.wikimedia.cloud
$ sudo docker ps # get container ID of wikibase-reconcile-webserver image
$ sudo docker exec -w /var/www/html <container_id> php maintenance/createAndPromote.php --bureaucrat <username> <password>
```

Users created this way can also create accounts for others at Special:CreateAccount.

### Cleanup

The `cleanup.yml` playbook removes most of the changes that the setup has caused:

```sh
$ cd extensions/WikibaseReconcileEdit/infrastructure
$ ansible-playbook cleanup.yml --limit <hostname>.wikidata-dev.eqiad1.wikimedia.cloud
```
