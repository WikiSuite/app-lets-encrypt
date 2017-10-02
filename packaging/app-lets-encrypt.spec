
Name: app-lets-encrypt
Epoch: 1
Version: 1.0.5
Release: 1%{dist}
Summary: Let's Encrypt
License: GPLv3
Group: ClearOS/Apps
Packager: WikiSuite
Vendor: WikiSuite
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base
Requires: app-certificate-manager

%description
Let's Encrypt is an open certificate authority that provides free SSL certificates.

%package core
Summary: Let's Encrypt - Core
License: LGPLv3
Group: ClearOS/Libraries
Requires: app-base-core
Requires: app-network-core
Requires: app-certificate-manager-core >= 1:2.4.0
Requires: app-events-core
Requires: app-tasks-core
Requires: certbot
Requires: python2-certbot-apache

%description core
Let's Encrypt is an open certificate authority that provides free SSL certificates.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/lets_encrypt
cp -r * %{buildroot}/usr/clearos/apps/lets_encrypt/

install -d -m 0755 %{buildroot}/var/clearos/events/lets_encrypt
install -d -m 0755 %{buildroot}/var/clearos/lets_encrypt
install -d -m 0755 %{buildroot}/var/clearos/lets_encrypt/backup
install -D -m 0644 packaging/app-lets-encrypt.cron %{buildroot}/etc/cron.d/app-lets-encrypt
install -D -m 0755 packaging/lets-encrypt-event %{buildroot}/var/clearos/events/lets_encrypt/lets_encrypt
install -D -m 0644 packaging/lets_encrypt.conf %{buildroot}/etc/clearos/lets_encrypt.conf

%post
logger -p local6.notice -t installer 'app-lets-encrypt - installing'

%post core
logger -p local6.notice -t installer 'app-lets-encrypt-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/lets_encrypt/deploy/install ] && /usr/clearos/apps/lets_encrypt/deploy/install
fi

[ -x /usr/clearos/apps/lets_encrypt/deploy/upgrade ] && /usr/clearos/apps/lets_encrypt/deploy/upgrade

exit 0

%preun
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-lets-encrypt - uninstalling'
fi

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-lets-encrypt-core - uninstalling'
    [ -x /usr/clearos/apps/lets_encrypt/deploy/uninstall ] && /usr/clearos/apps/lets_encrypt/deploy/uninstall
fi

exit 0

%files
%defattr(-,root,root)
/usr/clearos/apps/lets_encrypt/controllers
/usr/clearos/apps/lets_encrypt/htdocs
/usr/clearos/apps/lets_encrypt/views

%files core
%defattr(-,root,root)
%exclude /usr/clearos/apps/lets_encrypt/packaging
%exclude /usr/clearos/apps/lets_encrypt/unify.json
%dir /usr/clearos/apps/lets_encrypt
%dir /var/clearos/events/lets_encrypt
%dir /var/clearos/lets_encrypt
%dir /var/clearos/lets_encrypt/backup
/usr/clearos/apps/lets_encrypt/deploy
/usr/clearos/apps/lets_encrypt/language
/usr/clearos/apps/lets_encrypt/libraries
/etc/cron.d/app-lets-encrypt
/var/clearos/events/lets_encrypt/lets_encrypt
%config(noreplace) /etc/clearos/lets_encrypt.conf
