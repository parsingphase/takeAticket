Feature Requests
================

### To Do
    
* ~~Add admin interface for settings DB~~
    * Ensure that remoteUrl setting is valid? ~~(Or redirect to / on error)~~
* Put instrument parts on manage page in standard order
* Allow clicking in checkbox itself in "manage" toggle button (currently double-toggles)
* Allow multi-line messages
    * And/or change min block height for message-only tickets
* Floating Performing button in manage interface when edit panel is off screen?
* Edit existing performers (change names)
* Allow user to perform two parts (currently cannot save)
* Fix Z-index issue in manage page ([screenshot](images/zindex.png))
    * Workaround for narrow screen - clear song search box after selecting song
* Enable key controls 
    * Arrows in search
    * Button tab order?
* Use foreign keys in MySQL
* Add "platform" concept to replace temporary inRb3, inRb4 hack 
* Allow customisation of instrument selection, order, default usage
    * Eg for non-instrumental karaoke, possibly multiple vocal parts
* Add "session" concept to allow multiple sessions to be stored in DB without reset
* Rewrite the whole overcomplex mess in Angular ~~(/+Symfony?)~~
    
### Done

* ~~Grey-out (?) RB4 indicator on Manage page when Keytar in use~~
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
* ~~"Processing" and "Error" indicators on management interface~~
* ~~Non-song tickets~~
    * ~~Private and "blocks queue" options~~
* ~~Clearer display of when manage edit panel is editing existing rather than new ticket?~~
* ~~Display performers in instrument order on manage page~~
* ~~Remove song from ticket (can currently only replace)~~
* ~~Force title icon + title on manage tickets onto same line (span nowrap)~~

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