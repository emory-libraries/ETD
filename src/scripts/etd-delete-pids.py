from eulfedora.server import Repository
from eulfedora.server import DigitalObject
from eulfedora.models import  ReverseRelation
import argparse
import re
from pidservices.clients import PidmanRestClient
from sys import exit
import rdflib
from rdflib.namespace import RDF, Namespace
import pprint

parser = argparse.ArgumentParser()

parser.add_argument(
    '-f', '--file',
    help='Path to file containing a list of PIDs. Include one PID per line. Besure to include `emory:` at the begining of the PID.',
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

pp = pprint.PrettyPrinter(indent=2)

REPOMGMT = Namespace(rdflib.URIRef('info:fedora/fedora-system:def/relations-external#'))
repomgmt_ns = {'fedora-rels-ext': Namespace(u'http://pid.emory.edu/ns/2011/repo-management/#')}

class ParentRecord(DigitalObject):
    """
    Subclass to collect all the pids realated to an ETD. We use the `ReverseRelation` so we can be
    confident that the related pids are aware they are related to an ETD that needs to be deleated.
    The `Related` subclass ensures that the pid is only realated to one ETD.
    """
    author_info = ReverseRelation(relation=REPOMGMT.authorInfoFor, type=DigitalObject, multiple=True) or []
    original = ReverseRelation(REPOMGMT.isOriginalOf, type=DigitalObject, multiple=True) or []
    pdf = ReverseRelation(REPOMGMT.isPDFOf, type=DigitalObject, multiple=True) or []
    supplements = ReverseRelation(REPOMGMT.isSupplementOf, type=DigitalObject, multiple=True) or []

    def related(self):
        return {
            self.pid:{
                'Author Info(s)': [a.pid for a in self.author_info] if self.author_info is not None else [],
                'Original(s)': [o.pid for o in self.original] if self.original is not None else [],
                'PDF(s)': [p.pid for p in self.pdf] if self.pdf is not None else [],
                'Supplement(s)': [s.pid for s in self.supplements] if self.supplements is not None else []
            }
        }

    def get_related_pids(self):
        pids_to_delete = []
        [(pids_to_delete.append(p.pid)) for p in self.author_info]
        [(pids_to_delete.append(p.pid)) for p in self.original]
        [(pids_to_delete.append(p.pid)) for p in self.pdf]
        [(pids_to_delete.append(p.pid)) for p in self.supplements]
        return pids_to_delete

class RelatedRecord(DigitalObject):
    """
    Sublass used to double check that the related pid is only associated with one ETD.
    """
    is_author_info = ReverseRelation(relation=REPOMGMT.hasAuthorInfo, type=DigitalObject, multiple=True)
    is_original = ReverseRelation(REPOMGMT.hasOriginal, type=DigitalObject, multiple=True)
    is_pdf = ReverseRelation(REPOMGMT.hasPDF, type=DigitalObject, multiple=True)
    is_supplements = ReverseRelation(REPOMGMT.hasSupplement, type=DigitalObject, multiple=True)
    def auth_count(self):
        return len(self.is_author_info) <= 1
    def ori_count(self):
        return len(self.is_original) <= 1
    def pdf_count(self):
        return len(self.is_pdf) <= 1
    def supp_count(self):
        return len(self.is_supplements) <= 1
    def check(self):
        return True if self.auth_count() and self.ori_count() and self.pdf_count() and self.supp_count() else False


repo = Repository('https://some.rep', username='******', password='********')
client = PidmanRestClient('*******', '****', '****')

pids = []
pids_to_delete = []
pid_report = []

if args['file']:
    with open(args['file']) as f:
        pids = f.readlines()

elif args['pid']:
    pids.append(args['pid'])

else:
    print('You must suply a PID or a path to a file containg a list of pids')
    exit()

for pid in pids:
    etd = repo.get_object(pid.rstrip(), type=ParentRecord)

    pids_to_delete.append(etd.pid)
    pid_report.append(etd.related())

    for r in etd.get_related_pids():
        related = etd = repo.get_object(r, type=RelatedRecord)
        if related.check():
            pids_to_delete.append(related.pid)
        else:
            print related.pid + ' is related to multiple ETDs.'

pp.pprint(pid_report)

if not args['no_action']:
    print str(len(pids_to_delete)) + ' will be purged.'
    raw_input("Press Enter to continue...")

    for bad_pid in pids_to_delete:
        # TODO add error handeling for a pid that might have already been deleted.
        ark = bad_pid.split(':')[1]
        # Important: we must deactive ark first. Otherwise we'll get a 404 on the uri.
        client.update_target(type="ark", noid=ark, active=False)
        repo.purge_object(bad_pid)
