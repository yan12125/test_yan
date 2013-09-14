# -*- coding: UTF-8 -*-
import random
import json

class TextData(object):
    db = None
    data = None

    def __init__(self, db):
        self.db = db
        self.updateData()

    def updateData(self):
        tempData = self.db.execute(u'SELECT title,text FROM texts')
        self.data = dict(tempData)

    def getSentence(self, title):
        if title in self.data:
            # http://stackoverflow.com/questions/306400
            output = random.choice(json.loads(self.data[title]))
            # convert ucs-2 to utf-8
            return output.encode('utf-8')
