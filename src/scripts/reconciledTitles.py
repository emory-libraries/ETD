from eulfedora.server import Repository
from eulfedora.server import DigitalObject
import argparse
import re
from pidservices.clients import PidmanRestClient
from sys import exit
import rdflib
from rdflib.namespace import RDF, Namespace
import csv

parser = argparse.ArgumentParser()

parser.add_argument(
    '-f', '--file',
    help='Path to file containing a list of etds. Include one PID per line. Besure to include `emory:` at the begining of the PID.',
    required=False
)

parser.add_argument(
    '-p', '--pid',
    help='Pid to delete. Besure to include `emory:`',
    required=False
)

parser.add_argument(
    '-n', '--no-action',
    help = 'Flag to do a dry run to see what will be deleated.',
    required = False,
    action='store_true'
)

args = vars(parser.parse_args())

REPOMGMT = Namespace(rdflib.URIRef('info:fedora/fedora-system:def/relations-external#'))
repomgmt_ns = {'fedora-rels-ext': Namespace(u'http://pid.emory.edu/ns/2011/repo-management/#')}

repo = Repository('https://fedora.library.emory.edu:8443/fedora/', username='fedoraAdmin', password='B1gS3cr3t')
client = PidmanRestClient('https://pid.emory.edu', 'etd', 'IDontCare')

etds = []

if args['file']:
    with open(args['file']) as f:
        for pid in f:
            etds.append(repo.get_object(pid.rstrip(), type=ETD))

elif args['pid']:
    etds.append(repo.get_object(pid.rstrip(), type=ETD))

else:
    etd_objs = repo.get_objects_with_cmodel("info:fedora/emory-control:ETD-1.0")
    etd_file_objs = repo.get_objects_with_cmodel("info:fedora/emory-control:EtdFile-1.0")
    etds = etd_objs + etd_file_objs

print(len(etds))
count = 0;
with open('udated-arks.csv', 'w') as csvfile:

    fieldnames = ['PID', 'Reconciled Title', 'Old Title']
    writer = csv.DictWriter(csvfile, fieldnames=fieldnames)

    for etd in etds:
        ark = etd.pid.split(':')[1]
        pidmanName = client.get_ark(ark)['name']
        pidmanName = pidmanName.replace("\n", " ")

        if pidmanName != etd.label:
            count = count + 1
            if not args['no_action']:
                print('crap')
                # client.update_ark(noid=ark, name=etd.label)
                #
            writer.writerow({'PID': etd.pid, 'Reconciled Title': etd.label, 'Old Title': pidmanName})

print(len(etds))
print(count)
