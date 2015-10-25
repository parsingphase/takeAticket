Feature Requests
================

### To Do
    
* "Processing" and "Error" indicators on management interface
* Non-song tickets
    * Private and "blocks queue" options
* Floating Performing button in manage interface when edit panel is off screen?
* Clearer display of when manage edit panel is editing existing rather than new ticket?
* Edit existing performers (change names)
* Fix Z-index issue in manage page ([screenshot](images/zindex.png))
    * Workaround for narrow screen - clear song search box after selecting song
* Display performers in instrument order on manage page
* Use foreign keys in MySQL
* Add "platform" concept to replace temporary inRb3, inRb4 hack 
* Allow customisation of instrument selection, order, default usage
    * Eg for non-instrumental karaoke, possibly multiple vocal parts
* Add "session" concept to allow multiple sessions to be stored in DB without reset
    
### Done

* ~~Include instrument (rotate through icons)~~
* ~~Add CONTRIBUTING doc~~
* ~~Display band members rather than stored ticket title if available~~
* ~~Fix done / upcoming to be appropriate to that point~~
* ~~Duration data? - time left, estimated completion~~ - per-ticket
* ~~Background image?~~ - drop 'background.jpg' in 'www/local'
* ~~Letter dividers in name button list? | or M:~~
* ~~Proximity warning - highlight any performer repeats within 3 songs~~
* ~~Show ticket number AND position (include deleted in position - probably not, they'll be sorted to null)~~
* ~~Put "done" text in right place when clicking "Performing" button~~ - removed for now
* ~~Get jshint to complain when I leave console logging in. Anyone knows how to fix this in .jshintrc, please tell me!~~
* ~~Edit existing tickets - modify performers, song~~
* ~~Update usage docs~~
* ~~Enable use of MySQL as a backend~~
    * ~~Update startup docs to include MySQL setup / DB imports~~
* ~~Integrate simpleuser better (ensure all css etc is local)~~

### Deferred

* Announcements on private list -- create a dedicated 'backstage' page (output only)?
* Add code tests
* Warn on duplicate submission - warns on song search for now
* Total done/remaining indicator
* Colour codes/patches for names? Bold Zeros?
* Record ticket color?
* Self-submission
    * Add note 'Firstname initial, please'
* Make footers dynamic?
* Flag any user with more than 5 performances