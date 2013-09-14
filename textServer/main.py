#!/usr/bin/env python2
# -*- coding: UTF-8 -*-
from db import Db
from server import TextServer

if __name__ == '__main__':
    db = Db()
    server = TextServer(db)
    server.run()
