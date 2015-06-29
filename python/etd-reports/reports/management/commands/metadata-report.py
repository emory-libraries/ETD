# ETD Metadata Report
#
#   Copyright 2015 Emory University General Library
#
#   Licensed under the Apache License, Version 2.0 (the "License");
#   you may not use this file except in compliance with the License.
#   You may obtain a copy of the License at
#
#       http://www.apache.org/licenses/LICENSE-2.0
#
#   Unless required by applicable law or agreed to in writing, software
#   distributed under the License is distributed on an "AS IS" BASIS,
#   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#   See the License for the specific language governing permissions and
#   limitations under the License.

import unicodecsv as csv
import logging
import settings
import sys, os
import time

from collections import defaultdict
from django.core.management.base import BaseCommand, CommandError
from django.core.paginator import Paginator
from eulfedora.server import Repository
from eulfedora.util import PermissionDenied
from getpass import getpass, getuser
from optparse import make_option

logger = logging.getLogger(__name__)

class Command(BaseCommand):
    '''Fetch metadata from fedora
    '''
    help = __doc__

    option_list = BaseCommand.option_list + (
        make_option('--username',
                    action='store',
                    help='Username of fedora user to connect as'),
        make_option('--password',
                    action='store',
                    help='Password for fedora user,  password=  will prompt for password'),
        make_option('-R',
                    dest='fedora_root',
                    action='store',
                    help='The root location for the Fedora repo'),
        make_option('-U',
                    dest='fedora_mgmt_user',
                    action='store',
                    help='The management username for the Fedora repo'),
        make_option('-P',
                    dest='fedora_mgmt_user_password',
                    action='store',
                    help='Password for fedora management user,  password=  will prompt for password')
        )

    counts = defaultdict(int)

    def handle(self, *args, **options):
        self.verbosity = int(options['verbosity'])    # 1 = normal, 0 = minimal, 2 = all
        self.v_normal = 1

        settings.FEDORA_ROOT = options['fedora_root']

        # check required options
        if (not options['fedora_root']) and (not options['fedora_mgmt_user']):
            raise CommandError('Fedora Management Root and Username are required')
        else:
            if not options['fedora_mgmt_user_password'] or options['fedora_mgmt_user_password'] == '':
                options['fedora_mgmt_user_password'] = 'B1gS3cr3t'#getpass()
                settings.FEDORA_PASSWORD = options['fedora_mgmt_user_password']


        try:
            #connection to repository
            repo = Repository(root=settings.FEDORA_ROOT, username=options['fedora_mgmt_user'], password=options['fedora_mgmt_user_password'])
            pid_set = repo.get_objects_with_cmodel('info:fedora/emory-control:ETD-1.0')

            # get items from pid_set
            items = list(pid_set)
            self.counts['total'] = len(items)

            if items:
                self.stdout.write("Building Report... \n")

                report_name = "reports/output/metadata-report-%s.csv" % time.strftime("%Y%m%d-%H%M%S")
                writer = csv.writer(open(report_name, 'w'))
                writer.writerow(['PID','Author name','Title','Abstract','Table of Contents'])

                for obj in items:

                    pid = obj.pid

                    self.stdout.write("Adding %s... \n"%pid)

                    dc = obj.dc.content.serialize()
                    mods = obj.getDatastreamObject("MODS").content.serialize()

                    author = dc.split('<dc:creator>')[1].split('</dc:creator>')[0]

                    try:
                        title = dc.split('<dc:title>')[1].split('</dc:title>')[0]
                    except:
                        title = ''
                    try:
                        abstract = dc.split('<dc:description>')[1].split('</dc:description>')[0]
                    except:
                        abstract=''

                    try:
                        table_of_contents = mods.split('<mods:tableOfContents>')[1].split('</mods:tableOfContents>')[0]
                    except:
                        table_of_contents = ''

                    writer.writerow([pid,author,title,abstract,table_of_contents])

            # summarize what was done
            self.stdout.write("\n\n")
            self.stdout.write("Total number selected: %s\n" % self.counts['total'])

        except PermissionDenied:
            print "You don't have permission to view these DigitalObjects."
            # summarize what was done
            self.stdout.write("\n\n")
            self.stdout.write("Encountered error: %s\n" % 'PermissionDenied')



    def output(self, v, msg):
        '''simple function to handle logging output based on verbosity'''
        if self.verbosity >= v:
            self.stdout.write("%s\n" % msg)
