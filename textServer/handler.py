# -*- coding: UTF-8 -*-
import urlparse
import json
from BaseHTTPServer import BaseHTTPRequestHandler

from data import TextData

class TextServerHandler(BaseHTTPRequestHandler):
    db = None
    dataProvider = None

    def __init__(self, request, client_addr, server):
        self.dataProvider = TextData(self.db)
        BaseHTTPRequestHandler.__init__(self, request, client_addr, server)

    def do_POST(self):
        # read POST variables
        # http://stackoverflow.com/questions/4233218
        length = int(self.headers.getheader('content-length'))
        data = urlparse.parse_qs(self.rfile.read(length), keep_blank_values=True)

        self.send_response(200)
        # we don't want persistent connections, for fear that Ctrl+C is pressed when
        # there are still connections
        self.send_header('Connection', 'close')
        self.end_headers()

        if 'title' in data:
            # try this: "title=123&title=456", and log data['title']
            sentence = self.dataProvider.getSentence(data['title'][0])
            self.wfile.write(json.dumps({'msg': sentence}))
        else:
            self.wfile.write(json.dumps({u'error': u'The title should be specified'}))

    def log_message(self, formats, *args):
        BaseHTTPRequestHandler.log_message(self, formats, *args)
