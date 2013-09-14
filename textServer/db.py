# -*- coding: UTF-8 -*-
import MySQLdb
import json
import urllib2
import urllib

class Db(object):
    db = None
    cursor = None

    def __init__(self):
        self.login()

    def __del__(self):
        self.db.close()

    def getCredentials(self):
        print 'Get MySQL secrets from localhost'
        url = 'https://localhost/fb/test_yan/stable/wrapper.php'
        data = { 'action': 'get_mysql_credentials' }
        response = urllib2.urlopen(url=url, data=urllib.urlencode(data))
        return json.loads(response.read())
        
    def login(self):
        print 'Logging in...'
        secrets = self.getCredentials()
        self.db = MySQLdb.connect(host=secrets['sqlhost'], user=secrets['sqlusername'], passwd=secrets['mysqlPass'], db=secrets['dbname'], port=int(secrets['sqlPort']), charset='utf8')
        self.cursor = self.db.cursor()

    def execute(self, operation, parameters=None):
        self.cursor.execute(operation, parameters)
        return self.cursor.fetchall()
