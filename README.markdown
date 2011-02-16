Idea
====
A experimental spider to search for websites starting from a specific site.
Connect it to public API's to get nice statistics about websites.

Todo
====
	[X] Add count per site, when we found it again increase the number
	[ ] Create run script that forks the spider
	[ ] Connect to API's to get cool statistics
	[X] Get real HTTP code
	[ ] Log memory usage and other stats
	[ ] Make it extremly fast! Use message queue's, caching and no overhead
	[X] Use a noSQL Database
	[X] Use Zend_Http_Client for parsing
	[X] Optimize noSQL usage
	[ ] Create couchDb views for reports
	[X] Add adddate
	[ ] Use ZF components
	[ ] Automatic compact/clean CouchDB
	[ ] Setup SOLR to search the sites
	[ ] Fix memory leak when running for more than 2 hours
