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
import sys
import os
import itertools
import time
import random

from collections import defaultdict
from django.core.management.base import BaseCommand, CommandError
from django.core.paginator import Paginator
from eulfedora.server import Repository
from eulfedora.util import PermissionDenied, RequestFailed
from getpass import getpass, getuser
from optparse import make_option

logger = logging.getLogger(__name__)

class Command(BaseCommand):
    '''Fetch pids from fedora:
     1. Count articles by division.
     2. Couonts articles by author
     3. Couonts articles by lead author (first author)
    '''
    help = __doc__

    option_list = BaseCommand.option_list + (
        make_option('--username',
                    dest='user',
                    action='store',
                    help='Username of fedora user to connect as'),
        make_option('--password',
                    dest='pass',
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
                    help='Password for fedora management user,  password=  will prompt for password'),
        make_option('-n',
                    dest='total_number_of_pids',
                    action='store',
                    help='The number of pids to return from each query.')
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
                options['fedora_mgmt_user_password'] = getpass()
                settings.FEDORA_PASSWORD = options['fedora_mgmt_user_password']

        if options['user'] and not options['pass']:
             options['pass'] = getpass()

        if (not options['total_number_of_pids']):
            options['total_number_of_pids'] = 10
        else:
            options['total_number_of_pids'] = int(options['total_number_of_pids'])

        etds_by_status = {
            'published': {},
            'draft': {},
            'reviewed': {},
            'submitted': {},
            'approved': {},
            'inactive': {}}

        self.stdout.write("\n\n")
        self.stdout.write("Report will contain PIDs of the following status:")

        for status in dict(etds_by_status):
            self.stdout.write("\n * %s" % (status))

        self.stdout.write("\nEach status will contain at most %s PIDs.\n\n" % options['total_number_of_pids'])


        def sort_by_status(object, status, limit):

            if len(etds_by_status[status]) >= limit:
                self.stdout.write("Skipping %s with status: %s... \n"% (pid, etd_status))
            else:
                self.stdout.write("Adding %s... \n"%pid)
                self.stdout.write("ETDstatus: %s \n"%etd_status)

                dc = obj.dc.content.serialize()
                mods = obj.getDatastreamObject("MODS").content.serialize()
                rels_ext = obj.rels_ext.content.serialize()

                related_objs = []

                # Get related objects
                try:
                    for r in rels_ext.split('rdf:resource="')[1:]:
                        related_objs.append(r.split('"/>')[0])
                except:
                    related_objs.append('N/A')

                # Get creator as author
                author = dc.split('<dc:creator>')[1].split('</dc:creator>')[0]

                # Get Title
                try:
                    title = dc.split('<dc:title>')[1].split('</dc:title>')[0]
                except:
                    title = ''

                # Get Abstract
                try:
                    abstract = dc.split('<dc:description>')[1].split('</dc:description>')[0]
                except:
                    abstract=''

                # Get table of contents
                try:
                    table_of_contents = mods.split('<mods:tableOfContents>')[1].split('</mods:tableOfContents>')[0]
                except:
                    table_of_contents = ''

                row_content = [status, pid, related_objs, author, title, abstract, table_of_contents]

                etds_by_status[status][pid] = row_content

        try:
            #connection to repository
            repo = Repository(root=settings.FEDORA_ROOT, username=options['fedora_mgmt_user'], password=options['fedora_mgmt_user_password'])
            pid_set = repo.get_objects_with_cmodel('info:fedora/emory-control:ETD-1.0')

            # get items from pid_set
            items = list(pid_set)

            if items:
                self.stdout.write("Collecting PIDs for Report... \n")

                report_name = "reports/output/pid-sampling-report-%s.csv" % time.strftime("%Y%m%d-%H%M%S")
                writer = csv.writer(open(report_name, 'w'))
                writer.writerow(['ETD status','PID', 'Related PIDs', 'Author name','Title','Abstract','Table of Contents'])

                total = options['total_number_of_pids']

                for obj in items:
                    pid = obj.pid
                    rels_ext = obj.rels_ext.content.serialize()
                    etd_status = rels_ext.split('<fedora-rels-ext:etdStatus>')[1].split('</fedora-rels-ext:etdStatus>')[0]

                    if sum(len(v) for v in etds_by_status.itervalues()) < (len(dict(etds_by_status)) * total):
                        sort_by_status(obj, etd_status, total)
                        self.counts['processed']+=1
                    else:
                        break

        except PermissionDenied:
            print "You don't have permission to view these DigitalObjects."
            # summarize what was done
            self.stdout.write("\n\n")
            self.stdout.write("Encountered error: %s\n" % 'PermissionDenied')

        except RequestFailed:
            # summarize what was done
            self.stdout.write("There was an interruption in the connection to Fedora that cause the process to fail.")
            self.stdout.write("\n\n")
            self.stdout.write("Encountered error: %s\n" % 'RequestFailed')

        except (KeyboardInterrupt, SystemExit):
            self.stdout.write("\n\n")
            self.stdout.write("Stopping DB calls due to user interruption.")
            self.stdout.write("\n\n")

        # write a randomized list of pids to csv
        self.stdout.write("Adding PIDs to Report... \n")

        for status in dict(etds_by_status):
            for item in etds_by_status[status]:
                writer.writerow(etds_by_status[status][item])

        self.stdout.write("\nProcessed: %s PIDs \n" % self.counts['processed'])

        # summarize what was done using etds_by_status
        for status in dict(etds_by_status):
            self.stdout.write("\n\n")
            self.stdout.write("Total %s: %s\n" % (status, len(etds_by_status[status])))

    def output(self, v, msg):
        '''simple function to handle logging output based on verbosity'''
        if self.verbosity >= v:
            self.stdout.write("%s\n" % msg)
