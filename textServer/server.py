# -*- coding: UTF-8 -*-
import BaseHTTPServer

from handler import TextServerHandler
from data import TextData

class TextServer(BaseHTTPServer.HTTPServer):
    db = None

    def __init__(self, db):
        self.db = db
        self.createServer()
        self.registerRun(1)

    def __del__(self):
        self.registerRun(0)

    def createServer(self):
        port = 40000
        print 'Listening on port ' + str(port)
        server_addr = ('127.0.0.1', port)
        # Pass db to the handler
        # http://blog.thekondor.net/2013/05/pass-arguments-to-basehttprequesthandler.html
        handler = TextServerHandler
        handler.db = self.db
        BaseHTTPServer.HTTPServer.__init__(self, server_addr, handler)

    def registerRun(self, status):
        print 'Set textServer running status to ' + str(status)
        result = self.db.execute('SELECT value FROM main WHERE name="textServer_running"')

        if len(result) == 0:
            self.db.execute('INSERT INTO main (name,value) VALUES ("textServer_running", %d)', (status,))
        else:
            self.db.execute('UPDATE main SET value = %s WHERE name = "textServer_running" ', (status,))

    def run(self):
        try:
            self.serve_forever()
        except KeyboardInterrupt:
            self.server_close()
            # to make ^C in a independent line
            print ''
