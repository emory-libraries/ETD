# Fedora Repository settings
FEDORA_ROOT = 'https://fedora.library.emory.edu:8443/fedora/'
# FEDORA_ROOT = 'https://walden.library.emory.edu:8443/fedora/'

# by default, should run as a non-privileged user
FEDORA_USER = 'guest'
FEDORA_PASSWORD = ''
# maintenance account for scripts that need to ingest/modify content
FEDORA_MANAGEMENT_USER = 'fedoraAdmin'
FEDORA_MANAGEMENT_PASSWORD = ''
# test settings
FEDORA_TEST_ROOT = 'https://wlibdev002.library.emory.edu:8743/fedora/'
# credentials to purge test objects
FEDORA_TEST_USER = 'fedoraAdmin'
FEDORA_TEST_PASSWORD = 'fedoraAdmin'

INSTALLED_APPS = (
    'etd-reports.reports',
)
