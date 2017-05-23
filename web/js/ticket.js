/**
 * Created by wechsler on 04/08/15.
 */
var ticketer = (function() {
  'use strict';

  return {
    upcomingTicketTemplate: null,
    manageTemplate: null,
    songAutocompleteItemTemplate: null,
    editTicketTemplate: null,
    songDetailsTemplate: null,
    selfSubmitTemplate: null,
    manageInstrumentTabsTemplate: null,
    appMessageTarget: null,
    ticketSubmitTemplate: null,
    searchCount: 10,
    instrumentOrder: null,
    defaultSongLengthSeconds: 240,
    defaultSongIntervalSeconds: 120,
    messageTimer: null,
    lastUpdateHash: null,

    /**
     * @var {{songInPreview,upcomingCount,iconMapHtml,selfSubmission}}
     */
    displayOptions: {},

    /**
     * List of all performers (objects) who've signed up in this session
     */
    performers: [],

    /**
     * List of all platform names in the system
     */
    platforms: [],

    performerExists: function(performerName) {
      for (var i = 0; i < this.performers.length; i++) {
        if (this.performers[i].performerName.toLowerCase() === performerName.toLowerCase()) {
          return true;
        }
      }
      return false;
    },

    addPerformerByName: function(performerName) {
      this.performers.push({performerName: performerName});
      // Now resort it
      this.performers.sort(function(a, b) {
        return a.performerName.localeCompare(b.performerName);
      });
    },

    /**
     * Run the "upcoming" panel
     */
    go: function() {
      this.initTemplates();

      ticketer.reloadTickets();
      setInterval(function() {
        ticketer.reloadTickets();
      }, 10000);
    },

    sortBand: function(unsortedBand) {
      var sortedBand = {};
      if (this.instrumentOrder) {
        for (var i = 0; i < this.instrumentOrder.length; i++) {
          var instrument = this.instrumentOrder[i];
          if (unsortedBand.hasOwnProperty(instrument)) {
            sortedBand[instrument] = unsortedBand[instrument];
          }
        }
      } else {
        sortedBand = unsortedBand;
      }
      return sortedBand;
    },

    /**
     * Draw an "upcoming" ticket
     * @param ticket {{band}}
     * @returns {*}
     */
    drawDisplayTicket: function(ticket) {
      // Sort band into standard order
      var unsortedBand = ticket.band;
      ticket.band = this.sortBand(unsortedBand);
      var ticketParams = {ticket: ticket, icons: this.displayOptions.iconMapHtml};
      return this.upcomingTicketTemplate(ticketParams);
    },

    /**
     * Draw a "queue management" ticket
     * @param ticket
     * @returns {*}
     */
    drawManageableTicket: function(ticket) {
      ticket.used = Number(ticket.used); // Force int

      return this.manageTemplate({ticket: ticket});
    },

    /**
     * Reload all tickets on the upcoming page
     */
    reloadTickets: function() {
      var that = this;

      $.get('/api/next', function(tickets) {

        var out = '';
        for (var i = 0; i < tickets.length; i++) {
          var ticket = tickets[i];
          out += that.drawDisplayTicket(ticket);
        }

        var target = $('#target');
        target.html(out);

        target.find('.auto-font').each(
          function() {
            var fixedWidth = $(this).data('fixed-assetwidth');
            if (!fixedWidth) {
              fixedWidth = 0;
            }
            fixedWidth = Number(fixedWidth);
            if ((screen.width <= 480) && window.devicePixelRatio) {
              fixedWidth = fixedWidth / 2; // Mobile CSS
            }

            var spaceUsedByText = (this.scrollWidth - fixedWidth);
            var spaceAvailableForText = (this.clientWidth - fixedWidth);
            var rawScale = Math.max(spaceUsedByText / spaceAvailableForText, 1);
            var scale = 1.05 * rawScale;

            if (that.displayOptions.adminQueueHasControls && that.displayOptions.isAdmin) {
              scale *= 1.25;
            }

            // 1.05 extra scale to fit neatly, fixedWidth is non-scaling elements
            var font = Number($(this).css('font-size').replace(/[^0-9]+$/, ''));
            $(this).css('font-size', Number(font / scale).toFixed() + 'px');
          }
        );

        target.find('.performingButton').click(function() {
          var ticketId = $(this).data('ticket-id');
          if (window.confirm('Mark song as performing?')) {
            that.performButtonCallback(this);
          }
        });
        target.find('.removeButton').click(function() {
          var ticketId = $(this).data('ticket-id');
          if (window.confirm('Remove song?')) {
            that.removeButtonCallback(this);
          }
        });

      });
    },

    /**
     * Enable queue management ticket buttons in the specified element
     * @param topElement
     */
    enableButtons: function(topElement) {
      var that = this;

      $(topElement).find('.performButton').click(function() {
        that.performButtonCallback(this);
      });

      $(topElement).find('.removeButton').click(function() {
        that.removeButtonCallback(this);
      });

      $(topElement).find('.editButton').click(function() {
        that.editButtonCallback(this);
      });
    },

    /**
     * Activate the search box at the given location
     *
     * @param {string} songSearchInput Input field identifier
     * @param {string} songSearchResultsTarget Container for output list
     * @param {function} songClickHandler Function to call when a listed song is clicked
     */
    enableSongSearchBox: function(songSearchInput, songSearchResultsTarget, songClickHandler) {
      var that = this;
      $(songSearchInput).keyup(
        function() {
          var songComplete = $(songSearchResultsTarget);
          var input = $(this);
          var searchString = input.val();
          if (searchString.length >= 3) {
            $.ajax({
              method: 'POST',
              data: {
                searchString: searchString,
                searchCount: that.searchCount
              },
              url: '/api/songSearch',
              /**
               * @param {{songs, searchString}} data
               */
              success: function(data) {
                var songs = data.songs;
                if (input.val() == data.searchString) {
                  // Ensure autocomplete response is still valid for current input value
                  var out = '';
                  var song; // Used twice below
                  for (var i = 0; i < songs.length; i++) {
                    song = songs[i];
                    out += that.songAutocompleteItemTemplate({song: song});
                  }
                  songComplete.html(out).show();

                  // Now attach whole song as data:
                  for (i = 0; i < songs.length; i++) {
                    song = songs[i];
                    var songId = song.id;
                    songComplete.find('.acSong[data-song-id=' + songId + ']').data('song', song);
                  }

                  that.enableAcSongSelector(songComplete, songClickHandler);
                }
              },
              error: function(xhr, status, error) {
                void(error);
              }
            });
          } else {
            songComplete.html('');
          }
        }
      );
    },

    /**
     * Completely (re)generate the add ticket control panel and enable its controls
     * @param {?number} currentTicket Optional
     */
    resetEditTicketBlock: function(currentTicket) {
      var that = this;
      var controlPanelOuter = $('.editTicketOuter');

      // Current panel state in function scope
      var selectedInstrument = 'V';
      var currentBand = {};

      // Reset band to empty (or to ticket band state)
      for (var instrumentIdx = 0; instrumentIdx < that.instrumentOrder.length; instrumentIdx++) {
        var instrument = that.instrumentOrder[instrumentIdx];
        currentBand[instrument] = [];

        if (currentTicket && currentTicket.band) {
          // Ticket.band is a complex datatype. Current band is just one array of names per instrument. Unpack to show.
          if (currentTicket.band.hasOwnProperty(instrument)) {
            var instrumentPerformerObjects = currentTicket.band[instrument];
            for (var pIdx = 0; pIdx < instrumentPerformerObjects.length; pIdx++) {
              currentBand[instrument].push(instrumentPerformerObjects[pIdx].performerName);
            }
          }
        }
        // Store all instruments as arrays - most can only be single, but vocals is 1..n potentially
      }

      drawEditTicketForm(currentTicket);
      // X var editTicketBlock = $('.editTicket'); // only used in inner scope (applyNewSong)

      // Enable 'Add' button
      $('.editTicketButton').click(editTicketCallback);
      $('.cancelTicketButton').click(cancelTicketCallback);
      $('.removeSongButton').click(removeSong);

      $('.toggleButton').click(
        function() {
          var check = $(this).find('input[type=checkbox]');
          check.prop('checked', !check.prop('checked'));
        }
      );

      var ticketTitleInput = $('.editTicketTitle');

      // Copy band name into summary area on Enter
      ticketTitleInput.keydown(function(e) {
        if (e.keyCode === 13) {
          updateBandSummary();
        }
      });

      $('.newPerformer').keydown(function(e) {
        if (e.keyCode === 13) {
          var newPerformerInput = $('.newPerformer');
          var newName = newPerformerInput.val();
          if (newName.trim().length) {
            that.alterInstrumentPerformerList(currentBand, selectedInstrument, newName, true);
            // Now update band with new performers of this instrument
            updateInstrumentTabPerformers();
            rebuildPerformerList(); // Because performer allocations changed
            if (currentBand[selectedInstrument].length) { // If we've a performer for this instrument, skip to next
              nextInstrumentTab();
            }
          }
          newPerformerInput.val('');
        }
      });

      // Set up the song search box in this control panel and set the appropriate callback
      var songSearchInput = '.addSongTitle';
      var songSearchResultsTarget = '.songComplete';

      this.enableSongSearchBox(songSearchInput, songSearchResultsTarget, applyNewSong);

      // ************* Inner functions **************
      /**
       * Switch to the next visible instrument tab
       */
      function nextInstrumentTab() {
        // Find what offset we're at in instrumentOrder
        var currentOffset = 0;
        for (var i = 0; i < that.instrumentOrder.length; i++) {
          if (that.instrumentOrder[i] === selectedInstrument) {
            currentOffset = i;
          }
        }
        var nextOffset = currentOffset + 1;
        if (nextOffset >= that.instrumentOrder.length) {
          nextOffset = 0;
        }
        var instrument = that.instrumentOrder[nextOffset];
        selectedInstrument = instrument; // Reset before we redraw tabs
        var newActiveTab = setActiveTab(instrument);

        // Make sure we switch to a *visible* tab
        if (newActiveTab.hasClass('instrumentUnused')) {
          nextInstrumentTab();
        }
      }

      /**
       * (re)Draw the add/edit ticket control panel in the .editTicketOuter element
       */
      function drawEditTicketForm(ticket) {
        var templateParams = {performers: that.performers};
        if (ticket) {
          templateParams.ticket = ticket;
        }
        controlPanelOuter.html(that.editTicketTemplate(templateParams));
        if (ticket && ticket.song) {
          applyNewSong(ticket.song);
        }
        updateInstrumentTabPerformers();
        rebuildPerformerList(); // Initial management form display
      }


      /**
       * Rebuild list of performer buttons according to overall performers list
       * and which instruments they are assigned to
       *
       * TODO refactor so that the current standard method is as for the management page and calls an internal
       * function (buildPerformerList ?) with targetElement,Callback,instrument functions?
       */
      function rebuildPerformerList() {
        var newButton;
        var targetElement = controlPanelOuter.find('.performerControls');
        targetElement.text(''); // Remove existing list

        var lastInitial = '';
        var performerCount = that.performers.length;
        var letterSpan;
        for (var pIdx = 0; pIdx < performerCount; pIdx++) {
          var performerName = that.performers[pIdx].performerName;
          var performerInstrument = that.findPerformerInstrument(performerName, currentBand);
          var isPerforming = performerInstrument ? 1 : 0;
          var initialLetter = performerName.charAt(0).toUpperCase();
          if (lastInitial !== initialLetter) { // If we're changing letter
            if (letterSpan) {
              targetElement.append(letterSpan); // Stash the previous letterspan if present
            }
            letterSpan = $('<span class="letterSpan"></span>'); // Create a new span
            if ((performerCount > 15)) {
              letterSpan.append($('<span class="initialLetter">' + initialLetter + '</span>'));
            }
          }
          lastInitial = initialLetter;

          newButton = $('<span></span>');
          newButton.addClass('btn addPerformerButton');
          newButton.addClass(isPerforming ? 'btn-primary' : 'btn-default');
          if (isPerforming && (performerInstrument !== selectedInstrument)) { // Dim out buttons for other instruments
            newButton.attr('disabled', 'disabled');
          }
          newButton.text(performerName);
          newButton.data('selected', isPerforming); // This is where it gets fun - check if user is in band!
          letterSpan.append(newButton);
        }
        targetElement.append(letterSpan);

        // Enable the new buttons
        $('.addPerformerButton').click(function() {
          var name = $(this).text();
          var selected = $(this).data('selected') ? 0 : 1; // Reverse to get new state
          if (selected) {
            $(this).removeClass('btn-default').addClass('btn-primary');
          } else {
            $(this).removeClass('btn-primary').addClass('btn-default');
          }
          $(this).data('selected', selected); // Toggle

          that.alterInstrumentPerformerList(currentBand, selectedInstrument, name, selected);
          updateInstrumentTabPerformers();
          rebuildPerformerList(); // Because performer allocations changed
          if (currentBand[selectedInstrument].length) { // If we've a performer for this instrument, skip to next
            nextInstrumentTab();
          }
        });
      }

      /**
       * Handle click on edit ticket button
       */
      function editTicketCallback() {
        var titleInput = $('.editTicketTitle');
        var ticketTitle = titleInput.val();
        var songInput = $('.selectedSongId');
        var songId = songInput.val();
        var privateCheckbox = $('input.privateCheckbox');
        var isPrivate = privateCheckbox.is(':checked');
        var blockingCheckbox = $('input.blockingCheckbox');
        var isBlocked = blockingCheckbox.is(':checked');

        var data = {
          title: ticketTitle,
          songId: songId,
          band: currentBand,
          private: isPrivate,
          blocking: isBlocked
        };

        if (currentTicket) {
          data.existingTicketId = currentTicket.id;
        }

        that.showAppMessage('Saving ticket');

        $.ajax({
            method: 'POST',
            data: data,
            url: '/api/saveTicket',
            success: function(data, status) {
              that.showAppMessage('Saved ticket', 'success');

              void(status);
              var ticketId = data.ticket.id;

              var ticketBlockSelector = '.ticket[data-ticket-id="' + ticketId + '"]';
              var existingTicketBlock = $(ticketBlockSelector);
              if (existingTicketBlock.length) {
                // Replace existing
                existingTicketBlock.after(that.drawManageableTicket(data.ticket));
                existingTicketBlock.remove();
              } else {
                // Append new
                $('#target').append(that.drawManageableTicket(data.ticket));
              }

              var ticketBlock = $(ticketBlockSelector);
              ticketBlock.data('ticket', data.ticket);
              that.enableButtons(ticketBlock);

              if (data.performers) {
                that.performers = data.performers;
              }

              that.updatePerformanceStats();
              that.resetEditTicketBlock();

            },
            error: function(xhr, status, error) {
              var message = 'Ticket save failed';
              that.reportAjaxError(message, xhr, status, error);
              void(error);
              // FIXME handle error
            }
          }
        );
      }

      function cancelTicketCallback() {
        that.resetEditTicketBlock();
      }

      /**
       * Return tab corresponding to a given instrument abbreviation
       *
       * @param {string} instrument Abbreviation
       * @returns {jQuery}
       */
      function getTabByInstrument(instrument) {
        return controlPanelOuter.find('.instrument[data-instrument-shortcode=' + instrument + ']');
      }

      /**
       * Set the tab for the specified instrument abbreviation as active
       *
       * @param selectedInstrument
       * @returns {jQuery}
       */
      function setActiveTab(selectedInstrument) {
        var allInstrumentTabs = controlPanelOuter.find('.instrument');
        allInstrumentTabs.removeClass('instrumentSelected');
        var selectedTab = getTabByInstrument(selectedInstrument);
        selectedTab.addClass('instrumentSelected');
        rebuildPerformerList(); // Because current instrument context changed
        return selectedTab;
      }

      /**
       * Update the band summary line in the manage area
       */
      function updateBandSummary() {
        var bandName = $('.editTicketTitle').val();
        var members = [];
        for (var instrument in currentBand) {
          if (currentBand.hasOwnProperty(instrument)) {
            for (var i = 0; i < currentBand[instrument].length; i++) {
              members.push(currentBand[instrument][i]);
            }
          }
        }
        var memberList = members.join(', ');
        var summaryHtml = (bandName ? bandName + '<br />' : '') + memberList;
        $('.selectedBand').html(summaryHtml);
      }

      /**
       * Update all instrument tabs with either performer names or 'needed' note
       */
      function updateInstrumentTabPerformers() {
        var performersSpan;
        var performerString;

        for (var iIdx = 0; iIdx < that.instrumentOrder.length; iIdx++) {
          var instrument = that.instrumentOrder[iIdx];

          performersSpan = controlPanelOuter
            .find('.instrument[data-instrument-shortcode=' + instrument + ']')
            .find('.instrumentPerformer');

          performerString = currentBand[instrument].join(', ');
          if (!performerString) {
            performerString = '<i>Needed</i>';
          }
          performersSpan.html(performerString);
        }
        updateBandSummary();
      }

      /**
       * Handle click on a song in manage page search results
       *
       * @param {{id, title, artist, instruments}} song
       */
      function applyNewSong(song) {
        var selectedId = song.id;
        var selectedSong = song.artist + ': ' + song.title;

        var removeSongButton = $('.removeSongButton');
        removeSongButton.removeClass('hidden');

        // Perform actions with selected song
        var editTicketBlock = $('.editTicket'); // Temp hack, should already be in scope?

        editTicketBlock.find('input.selectedSongId').val(selectedId);
        editTicketBlock.find('.selectedSong').text(selectedSong);

        // Redraw instrument tabs according to current songs
        var instrumentDiv = controlPanelOuter.find('.instruments');
        instrumentDiv.html(that.manageInstrumentTabsTemplate(song.instruments));
        instrumentDiv.find('.instrument').removeClass('instrumentSelected');
        instrumentDiv.find('.instrument:first').addClass('instrumentSelected');

        // Enable the instrument tabs
        // Var allInstrumentTabs = controlPanelOuter.find('.instrument');

        instrumentDiv.find('.instrument').click(
          function() {
            selectedInstrument = $(this).data('instrumentShortcode');
            setActiveTab(selectedInstrument);
          }
        );

        // Iterate through currentBand and remove any instruments not present in song abbreviations
        var validInstruments = song.instruments.map(function(i) {
          return i.abbreviation;
        });
        for (var instrument in currentBand) {
          if (validInstruments.indexOf(instrument) === -1) {
            currentBand[instrument] = [];
          }
        }

        updateInstrumentTabPerformers();
        rebuildPerformerList(); // Because song changed
      }

      function removeSong() {
        var editTicketBlock = $('.editTicket'); // Temp hack, should already be in scope?
        editTicketBlock.find('input.selectedSongId').val(0);
        editTicketBlock.find('.selectedSong').text('');
        $(songSearchInput).val('');
        var removeSongButton = $('.removeSongButton');
        removeSongButton.hide();

      }
    },

    manage: function(tickets) {
      var that = this;
      this.appMessageTarget = $('#appMessages');
      this.initTemplates();
      var ticket, ticketBlock; // For loop iterations

      if (this.displayOptions.selfSubmission) {
        // Enable warning on hash change
        var updateWarning = $('#updateWarning');

        $.get('/api/updateHash', function(data) {
          that.lastUpdateHash = data.hash;
          updateWarning.find('.btn').click(function() {
            window.location.reload(true);
          });
        });

        window.setInterval(function() {
            $.get('/api/updateHash', function(data) {
              // X window.alert('Tickets: '+$('.ticket').length + ' / '+ data.hash  + ' / ' + that.lastUpdateHash);
              if ((that.lastUpdateHash !== data.hash) && (data.hash !== $('.ticket').length)) {
                // Note: abusing "opaque" hash here to avoid false-positives! (assuming it's undeleted count)
                updateWarning.show();
              }
            });
          },
          10000);
      }

      var out = '';
      for (var i = 0; i < tickets.length; i++) {
        ticket = tickets[i];
        out += that.drawManageableTicket(ticket);
        ticketBlock = $('.ticket[data-ticket-id="' + ticket.id + '"]');
        ticketBlock.data('ticket', ticket);
      }
      $('#target').html(out);

      // Find new tickets (now they're DOM'd) and add data to them
      for (i = 0; i < tickets.length; i++) {
        ticket = tickets[i];
        ticketBlock = $('.ticket[data-ticket-id="' + ticket.id + '"]');
        ticketBlock.data('ticket', ticket);
      }

      var $sortContainer = $('.sortContainer');
      $sortContainer.sortable({
        axis: 'y',
        update: function(event, ui) {
          void(event);
          void(ui);
          that.ticketOrderChanged();
        }
      }).disableSelection().css('cursor', 'move');

      this.enableButtons($sortContainer);

      this.updatePerformanceStats();

      this.resetEditTicketBlock();

    },

    initSearchPage: function() {
      var that = this;
      this.initTemplates();
      this.enableSongSearchBox('.searchString', '.songComplete', that.searchPageSongSelectionClick);
    },

    initTemplates: function() {
      var that = this;

      // CommaList = each, with commas joining. Returns value at t as tuple {k,v}
      // "The options hash contains a function (options.fn) that behaves like a normal compiled Handlebars template."
      // If called without inner template, options.fn is not populated
      Handlebars.registerHelper('commalist', function(context, options) {
        var retList = [];

        for (var key in context) {
          if (context.hasOwnProperty(key)) {
            retList.push(options.fn ? options.fn({k: key, v: context[key]}) : context[key]);
          }
        }

        return retList.join(', ');
      });

      Handlebars.registerHelper('instrumentIcon', function(instrumentCode) {
        var icon = '<span class="instrumentTextIcon">' + instrumentCode + '</span>';
        if (that.displayOptions.hasOwnProperty('iconMapHtml')) {
          if (that.displayOptions.iconMapHtml.hasOwnProperty(instrumentCode)) {
            icon = that.displayOptions.iconMapHtml[instrumentCode];
          }
        }
        return new Handlebars.SafeString(icon);
      });

      Handlebars.registerHelper('durationToMS', function(duration) {
        var seconds = (duration % 60);
        if (seconds < 10) {
          seconds = '0' + seconds;
        }
        return Math.floor(duration / 60) + ':' + seconds;
      });

      Handlebars.registerHelper('gameList', function(song) {
        return song.platforms.join(', ');
      });

      Handlebars.registerHelper('ifContains', function(haystack, needle, options) {
        return (haystack.indexOf(needle) === -1) ? '' : options.fn(this);
      });

      this.manageTemplate = Handlebars.compile(
        '<div class="ticket well well-sm {{#if ticket.used}}used{{/if}}' +
        ' {{#if ticket.song}}{{#each ticket.song.platforms }}platform{{ this }} {{/each}}{{/if}}' +
        ' {{#if ticket.band.K}}withKeys{{/if}}"' +
        ' data-ticket-id="{{ ticket.id }}">' +
        '        <div class="pull-right">' +
        (function() {
          var s = '';
          for (var i = 0; i < that.platforms.length; i++) {
            var p = that.platforms[i];
            s += '<div class="gameMarker gameMarker' + p + '">' +
              '{{#if ticket.song}}{{#ifContains ticket.song.platforms "' + p + '" }}' + p +
              '{{/ifContains}}{{/if}}</div>';
          }
          return s;
        })() +
        '        <button class="btn btn-primary performButton" data-ticket-id="{{ ticket.id }}">Performing</button>' +
        '        <button class="btn btn-danger removeButton" data-ticket-id="{{ ticket.id }}">Remove</button>' +
        '        <button class="btn editButton" data-ticket-id="{{ ticket.id }}">' +
        '<span class="fa fa-edit" title="Edit"></span>' +
        '</button>' +
        '        </div>' +
        '<div class="ticketOrder">' +
        '<div class="ticketOrdinal"></div>' +
        '<div class="ticketTime"></div>' +
        '</div>' +
        '<div class="ticketId">' +
        '<span class="fa fa-ticket"></span> {{ ticket.id }}</div> ' +
        '<div class="ticketMeta">' +
        '<div class="blocking">' +
        '{{#if ticket.blocking}}<span class="fa fa-hand-stop-o" title="Blocking" />{{/if}}' +
        '</div>' +
        '<div class="private">' +
        '{{#if ticket.private}}<span class="fa fa-eye-slash" title="Private" />{{/if}}' +
        '</div>' +
        '</div>' +
        '<div class="pendingSong">' +
        '<span class="fa fa-group"></span> ' +

        // Display performers with metadata if valid, else just the band title.
        /*
         '{{#if ticket.performers}}' +
         '{{#each ticket.performers}}' +
         '<span class="performer performerDoneCount{{songsDone}}" ' +
         'data-performer-id="{{performerId}}"> {{performerName}} ' +
         ' (<span class="songsDone">{{songsDone}}</span>/<span class="songsTotal">{{songsTotal}}</span>)' +
         '</span>' +
         '{{/each}}' +
         '{{else}}' +
         '{{ ticket.title }}' +
         '{{/if}}' +
         */

        // Display performers with metadata if valid, else just the band title.
        '{{#if ticket.band}}' +
        '{{#each ticket.band}} <span class="instrumentTextIcon">{{ @key }}</span>' +
        '{{#each this}}' +
        '<span class="performer performerDoneCount{{songsDone}}" ' +
        'data-performer-id="{{performerId}}" data-performer-name="{{performerName}}"> {{performerName}} ' +
        ' (<span class="songsDone">{{songsDone}}</span>/<span class="songsTotal">{{songsTotal}}</span>)' +
        '</span>' +
        '{{/each}}' +
        '{{/each}}' +
        '{{/if}}' +

        '{{#if ticket.title}}' +
        '<span class="ticketTitleIcon"><span class="instrumentTextIcon">Title</span> {{ ticket.title }}</span>' +
        '{{/if}}' +

        '{{#if ticket.song}}<br /><span class="fa fa-music"></span> {{ticket.song.artist}}: ' +
        '{{ticket.song.title}}' +
        ' ({{gameList ticket.song}})' +
        '{{/if}}' +
        '</div>' +
        '</div>'
      );

      this.upcomingTicketTemplate = Handlebars.compile(
        '<div class="ticket well ' +
        (this.displayOptions.songInPreview ? 'withSong' : 'noSong') +
        ' ' +
        (this.displayOptions.title ? 'withTitle' : 'noTitle') + // TODO is this used (correctly)?
        '" data-ticket-id="{{ ticket.id }}">' +

        (this.displayOptions.adminQueueHasControls && this.displayOptions.isAdmin ?
          '<div class="ticketAdminControls">' +
          '<button class="btn btn-sm btn-primary performingButton"' +
          ' data-ticket-id="{{ ticket.id }}">Performing</button>' +
          '<button class="btn btn-sm btn-danger removeButton" data-ticket-id="{{ ticket.id }}">Remove</button>' +
          '</div>'
          : '') +


        '<div class="ticketMeta">' +
        '<div class="blocking">' +
        '{{#if ticket.blocking}}<span class="fa fa-hand-stop-o" title="Blocking" />{{/if}}' +
        '</div>' +
        '<div class="private">' +
        '{{#if ticket.private}}<span class="fa fa-eye-slash" title="Private" />{{/if}}' +
        '</div>' +
        '</div>' +

        '  <div class="ticket-inner">' +
        '    <p class="text-center band auto-font">{{ticket.title}}</p>' +
        '    <p class="performers auto-font" data-fixed-assetwidth="200">' +
        '{{#each ticket.band}}' +
        '<span class="instrumentTag">{{instrumentIcon @key}}</span>' +
        '<span class="instrumentPerformers">{{#commalist this}}{{v.performerName}}{{/commalist}}</span>' +
        '{{/each}}' +
        '    </p>' +
        (this.displayOptions.songInPreview ?
          '{{#if ticket.song}}<p class="text-center song auto-font">' +
          '{{ticket.song.artist}}: {{ticket.song.title}}' +
          ' ({{gameList ticket.song}})' +
          '</p>{{/if}}' : '') +
        '        </div>' +
        '</div>  '
      );

      this.songAutocompleteItemTemplate = Handlebars.compile(
        '<div class="acSong" data-song-id="{{ song.id }}">' +
        '        <div class="acSong-inner {{#if song.queued}}queued{{/if}}">' +
        '        {{song.artist}}: {{song.title}} ({{gameList song}}) ' +
        '        </div>' +
        '</div>  '
      );

      this.editTicketTemplate = Handlebars.compile(
        '<div class="editTicket well">' +
        '<div class="pull-right editTicketButtons">' +
        '<button class="blockingButton btn btn-warning toggleButton">' +
        '<span class="fa fa-hand-stop-o" /> Blocking ' +
        ' <input type="checkbox" class="blockingCheckbox" ' +
        '  {{#if ticket}}{{# if ticket.blocking }}checked="checked"{{/if}}{{/if}} /></button>' +
        '<button class="privacyButton btn btn-warning toggleButton">' +
        '<span class="fa fa-eye-slash" /> Private ' +
        ' <input type="checkbox" class="privateCheckbox" ' +
        '  {{#if ticket}}{{# if ticket.private }}checked="checked"{{/if}}{{/if}} /></button>' +
        '<button class="editTicketButton btn btn-success">' +
        '<span class="fa fa-save" /> Save</button>' +
        '<button class="cancelTicketButton btn">' +
        '<span class="fa fa-close" /> Cancel</button>' +
        '</div>' +

        '{{# if ticket}}' +
        '<h3 class="editTicketHeader">Edit ticket <span class="fa fa-ticket"></span> {{ticket.id}}</h3>' +
        '{{else}}<h3 class="newTicketHeader">Add new ticket</h3>{{/if}}' +

        '<div class="editTicketInner">' +
        '<div class="editTicketSong">' +
        '<div class="ticketAspectSummary"><span class="fa fa-music fa-2x" title="Song"></span> ' +
        '<input type="hidden" class="selectedSongId"/> ' +
        '<span class="selectedSong">{{#if ticket}}{{#if ticket.song}}{{ticket.song.artist}}: ' +
        '{{ticket.song.title}}{{/if}}{{/if}}</span>' +

        '<button title="Remove song from ticket" ' +
        'class="btn removeSongButton{{#unless ticket}}{{#unless ticket.song}} hidden{{/unless}}{{/unless}}">' +
        ' <span class="fa fa-ban" />' +
        '</button>' +

        '</div>' +
        '<div class="input-group input-group">' +
        '<span class="input-group-addon" id="search-addon1"><span class="fa fa-search"></span> </span>' +
        '<input class="addSongTitle form-control" placeholder="Search song or use code"/>' +
        '</div>' +

        '<div class="songCompleteOuter">' +
        '<div class="songComplete"></div>' +
        '</div>' + // /songCompleteOuter
        '</div>' + // /editTicketSong

        '<div class="editTicketBandColumn">' +

        '<div class="ticketAspectSummary"><span class="fa fa-group fa-2x pull-left" title="Performers"></span>' +
        '<span class="selectedBand">{{#if ticket}}{{ticket.title}}{{/if}}</span>' +
        '</div>' + // /ticketAspectSummary

        '<div class="input-group">' +
        '<span class="input-group-addon" id="group-addon-band"><span class="fa fa-pencil"></span> </span>' +
        '<input class="editTicketTitle form-control" placeholder="Band name or message (optional)"' +
        ' value="{{#if ticket}}{{ticket.title}}{{/if}}"/>' +
        '</div>' + // /input-group

        '<div class="bandControls">' +
        '<div class="bandTabsOuter">' +
        '<div class="instruments">' +
        '</div>' + // /instruments
        '<div class="performerSelect">' +
        '<div class="input-group input-group">' +
        '<span class="input-group-addon" id="group-addon-performer"><span class="fa fa-plus"></span> </span>' +
        '<input class="newPerformer form-control" placeholder="New performer (Firstname Initial)"/>' +
        '</div>' +

        '<div class="performerControls"></div>' +
        '</div>' + // /performerSelect
        '</div>' + // /bandTabsOuter
        '</div>' + // /bandControls
        '</div>' + // /editTicketBandColumn
        '<div class="clearfix"></div>' + // Clear after editTicketBandColumn
        '</div>' + // /editTicketInner
        '</div>' // /editTicket
      );

      this.manageInstrumentTabsTemplate = Handlebars.compile(
        '{{#each this}}' +
        ' <div class="instrument instrument{{ this.abbreviation }}" ' +
        '   data-instrument-shortcode="{{ this.abbreviation }}">' +
        '  <div class="instrumentName">{{ this.name }}</div>' +
        '  <div class="instrumentPerformer"><i>Needed</i></div>' +
        ' </div>' +
        '{{/each}}'
      );

      this.songDetailsTemplate = Handlebars.compile(
        '<div class="songDetails"><h3>{{song.artist}}: {{song.title}}</h3>' +
        '<table>' +
        '<tr><th>Duration</th><td>{{durationToMS song.duration}}</td></tr> ' +
        '<tr><th>Code</th><td>{{song.codeNumber}}</td></tr> ' +
        '<tr><th>Instruments </th><td>{{commalist song.instrumentNames}}</td></tr> ' +
        '<tr><th>Games</th><td>{{commalist song.platforms}}</td></tr> ' +
        '<tr><th>Source</th><td>{{song.source}}</td></tr> ' +
        '</table>' +
        '<span id="performSongButton" class="btn btn-success btn-lg" ' +
        'style="display: none; margin-top: 6px;">Perform this song</span> ' +
        '</div>'
      );

      this.selfSubmitTemplate = Handlebars.compile(
        // { song, players }
        '<p>Enter each performer as "firstname initial" (eg "David B") the same way each time,' +
        'so that we can uniquely identify you and ensure everyone gets an equal chance to perform. ' +
        'Performers can have up to three pending songs in the queue at a time ' +
        '(shown with <span class="fa fa-pause"></span> for users with 3+).</p>' +
        '<table class="table table-striped">' +
        '{{#each song.instruments}}' +
        '<tr class="instrumentRow" data-instrument="{{ this.abbreviation }}">' +
        '<td class="performerControlsCell"><p><b>{{ this.name }} performer</b></p>' +
        '<div class="performerControls"></div>' +
        '<p>Or add a new name: <input class="performer" data-instrument="{{ this.abbreviation }}"/></p>' +
        '</td><td class="noMobile"><span class="fa fa-arrow-right fa-3x" style="color: #999"></span></td>' +
        '<td><p><b>{{ this.name }}</b></p><div class="performerList performerList_{{ this.abbreviation }}"></div> ' +
        '</td></tr>{{/each}}' +
        '</table>'
      );

      this.ticketSubmitTemplate = Handlebars.compile(
        '<h4>Your Request Slip <span class="btn btn-primary submitUserTicketButton">Submit this</span></h4>' +
        '<div class="songDetails"><p><b>{{song.artist}}: {{song.title}}</b></p>' +
        // '<div class="bandName"><input type="text" placeholder="Your band name (optional)"/></div> ' +
        '{{#each band}}' +
        '<span class="instrumentTag">{{instrumentIcon @key}}</span> ' +
        '<span class="instrumentPerformers">{{commalist this}}</span><br />' +
        '{{/each}}' +
        '</div>');
    },

    ticketOrderChanged: function() {
      var that = this;
      var idOrder = [];
      $('#target').find('.ticket').each(
        function() {
          var ticketBlock = $(this);
          var ticketId = ticketBlock.data('ticketId');
          idOrder.push(ticketId);
        }
      );

      that.showAppMessage('Updating ticket order');
      $.ajax({
        method: 'POST',
        data: {
          idOrder: idOrder
        },
        url: '/api/newOrder',
        success: function(data, status) {
          // FIXME check return status
          that.showAppMessage('Saved revised order', 'success');
        },
        error: function(xhr, status, error) {
          var message = 'Failed to save revised order';
          that.reportAjaxError(message, xhr, status, error);
        }
      });

      this.updatePerformanceStats();
    },

    performButtonCallback: function(button) {
      var that = this;

      button = $(button);
      var ticketId = button.data('ticketId');
      that.showAppMessage('Mark ticket used');
      $.ajax({
          method: 'POST',
          data: {
            ticketId: ticketId
          },
          url: '/api/useTicket',
          success: function(data, status) {
            that.showAppMessage('Marked ticket used', 'success');
            void(data);
            void(status);
            var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
            ticketBlock.addClass('used');
            // TicketBlock.append(' (done)');

            // Fixme receive updated ticket info from API
            var ticket = ticketBlock.data('ticket');
            ticket.startTime = Date.now() / 1000;
            ticket.used = true;
            ticketBlock.data('ticket', ticket);

            that.updatePerformanceStats();
          },
          error: function(xhr, status, error) {
            var message = 'Failed to mark ticket used';
            that.reportAjaxError(message, xhr, status, error);
          }
        }
      );
    },

    removeButtonCallback: function(button) {
      var that = this;
      button = $(button);
      var ticketId = button.data('ticketId');
      that.showAppMessage('Deleting ticket');
      $.ajax({
          method: 'POST',
          data: {
            ticketId: ticketId
          },
          url: '/api/deleteTicket',
          success: function(data, status) {
            that.showAppMessage('Deleted ticket', 'success');
            void(status);
            var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
            ticketBlock.remove();
            that.updatePerformanceStats();
          },
          error: function(xhr, status, error) {
            var message = 'Failed to deleted ticket';
            that.reportAjaxError(message, xhr, status, error);
          }
        }
      );
    },

    editButtonCallback: function(button) {
      var that = this;
      button = $(button);
      var ticketId = button.data('ticketId');

      var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
      var ticket = ticketBlock.data('ticket'); // TODO possibly load from ajax instead?
      that.resetEditTicketBlock(ticket);
    },

    enableAcSongSelector: function(outerElement, songClickHandler) {
      var that = this;
      outerElement.find('.acSong').click(
        function() {
          // Find & decorate clicked element
          outerElement.find('.acSong').removeClass('selected');
          $(this).addClass('selected');

          var song = $(this).data('song');
          songClickHandler.call(that, song); // Run in 'that' context
        }
      );
    },


    searchPageSongSelectionClick: function(song) {
      // Don't allow "Perform this song" if it's already in the queue (song.queued)
      if (song.queued && this.displayOptions.selfSubmission) {
        window.alert('Song taken, please choose another');
        return;
      }

      var target = $('#searchTarget');
      song.instrumentNames = song.instruments.map(function(s) {
        return s.name;
      }); // Unwrap objects
      target.html(this.songDetailsTemplate({song: song}));
      $('#userTicketConfirmFormOuter').html('').hide(); // Remove older messages

      if (this.displayOptions.selfSubmission) {
        var that = this;
        target.find('#performSongButton').show().click(function() {
          that.performSongButtonClick(song);
        });
      }
      // Scroll to choice
      $('html, body').animate({
        scrollTop: (target.offset().top)
      }, 50);
    },

    /**
     * Callback that opens & builds the self-submission form
     *
     * @param song
     */
    performSongButtonClick: function(song) {
      $('.songComplete').hide();
      var userSubmitFormOuter = $('#userSubmitFormOuter');
      userSubmitFormOuter.html(this.selfSubmitTemplate({song: song}));

      var band = {};
      var that = this;

      this.reloadPerformers(function() {
        that.drawPerformerButtonsForAllInstruments(userSubmitFormOuter, band, song);

        // Also enable text input
        // Copy band name into summary area on Enter
        userSubmitFormOuter.find('input.performer').keydown(function(e) {
          if (e.keyCode === 13) {
            var input = $(this);
            var instrument = input.data('instrument');
            var performer = input.val().trim();
            if (performer.match(/\w+\s\w+/)) {
              band[instrument] = band[instrument] ? band[instrument] : [];
              that.alterInstrumentPerformerList(band, instrument, performer, true);
              var containingRow = $('.instrumentRow[data-instrument=' + instrument + ']');
              // Display players under instrument
              $(containingRow).find('.performerList').text(band[instrument].join(', ')); // FIXME display more neatly?
              // after all changes, redraw ALL buttons
              that.drawPerformerButtonsForAllInstruments($('#userSubmitFormOuter'), band, song);

              input.val('');
              that.drawConfirmTicketFormIfValid('#userTicketConfirmFormOuter', band, song);
            } else {
              window.alert('Name format must be Forename Initial');
            }
          }
        });

        // END enable text input

        userSubmitFormOuter.show();
        $('html, body').animate({
          scrollTop: (userSubmitFormOuter.offset().top)
        }, 50);
      });
    },

    /**
     *
     * @param userSubmitFormOuter
     * @param band
     * @param song
     */
    drawPerformerButtonsForAllInstruments: function(userSubmitFormOuter, band, song) {
      var that = this;
      userSubmitFormOuter.find('tr.instrumentRow').each(
        function() {
          var element = $(this);
          var instrument = element.data('instrument');
          that.drawPerformerButtonsForInstrumentInBand(element.find('.performerControls'), instrument, band, song);
        }
      );
    },

    /**
     * Compare rebuildPerformerList (Manage Tickets page)
     *
     * @param targetElement
     * @param instrumentCode
     * @param band
     * @param song
     */
    drawPerformerButtonsForInstrumentInBand: function(targetElement, instrumentCode, band, song) {
      var that = this;
      var clickCallback = function() {
        var button = $(this);
        if (button.attr('disabled')) { // Ignore these buttons
          return;
        }
        var instrument = button.data('instrument');
        var performer = button.data('performer');
        band[instrument] = band[instrument] ? band[instrument] : [];
        var existingInstrument = that.findPerformerInstrument(performer, band);
        if (existingInstrument) {
          // TODO If it's this instrument, remove this user
          that.alterInstrumentPerformerList(band, instrument, performer, false);
          // TODO If it's another instrument… we may have an issue (interface should (be made to) block this)
        } else {
          // Add this performer
          that.alterInstrumentPerformerList(band, instrument, performer, true);
        }

        var containingRow = $('.instrumentRow[data-instrument=' + instrument + ']'); // Display players under instrument
        $(containingRow).find('.performerList').text(band[instrument].join(', ')); // FIXME display more neatly?

        // after all changes, redraw ALL buttons
        that.drawPerformerButtonsForAllInstruments($('#userSubmitFormOuter'), band, song);

        that.drawConfirmTicketFormIfValid('#userTicketConfirmFormOuter', band, song);
      };

      // $(targetElement).html('PerformerButtons '+ instrumentCode);
      var newButton;
      // FIXME: need to load latest performers list
      targetElement.text(''); // Remove existing list

      var lastInitial = '';
      var performerCount = this.performers.length; // Legitimately global (performers is app-wide)
      var letterSpan;
      for (var pIdx = 0; pIdx < performerCount; pIdx++) {
        var performer = this.performers[pIdx];
        var performerName = performer.performerName;
        var performerInstrument = this.findPerformerInstrument(performerName, band);
        var isPerforming = performerInstrument ? 1 : 0;
        var initialLetter = performerName.charAt(0).toUpperCase();
        if (lastInitial !== initialLetter) { // If we're changing letter
          if (letterSpan) {
            targetElement.append(letterSpan); // Stash the previous letterspan if present
          }
          letterSpan = $('<span class="letterSpan"></span>'); // Create a new span
          if ((performerCount > 15)) {
            letterSpan.append($('<span class="initialLetter">' + initialLetter + '</span>'));
          }
        }
        lastInitial = initialLetter;

        newButton = $('<span></span>');
        newButton.text(performerName);
        newButton.addClass('btn addPerformerButton').data({
          instrument: instrumentCode,
          performer: performerName
        });

        newButton.addClass(isPerforming ? 'btn-primary' : 'btn-default');
        if (isPerforming && (performerInstrument !== instrumentCode)) { // Dim out buttons for other instruments
          newButton.attr('disabled', 'disabled');
        }

        if (performer.songsPending > 2) {
          newButton.attr('disabled', 'disabled');
          newButton.addClass('notAvailable');
          newButton.html(newButton.html() + ' <span class="fa fa-pause"></span>');
        }

        newButton.data('selected', isPerforming); // This is where it gets fun - check if user is in band!
        letterSpan.append(newButton);
      }
      targetElement.append(letterSpan);
      targetElement.find('.addPerformerButton').click(clickCallback);
    },

    bandMemberCount: function(bandObject) {
      var count = 0;
      for (var instrument in bandObject) {
        if (bandObject.hasOwnProperty(instrument)) {
          if (Array.isArray(bandObject[instrument])) {
            count += bandObject[instrument].length;
          }
        }
      }
      return count;
    },

    drawConfirmTicketFormIfValid: function(element, band, song) {
      var formBlock = $(element);
      var ticket = {};
      var that = this;

      // X console.log(['song', song]);
      // X console.log(['band', band, this.bandMemberCount(band)]);

      if (song && song.id && band && this.bandMemberCount(band)) {
        formBlock.show();
        formBlock.html('TICKET FORM');
        ticket.song = song;
        ticket.songId = song.id;
        ticket.band = band;

        formBlock.html(this.ticketSubmitTemplate(ticket));

        formBlock.find('.submitUserTicketButton').click(function() {
          $.ajax({
              method: 'POST',
              data: ticket,
              url: '/api/saveTicket',
              success: function(data, status) {
                void(status);

                if (data.ticket) {
                  that.showAppMessage('Saved ticket', 'success');
                  formBlock.html('<div class="alert alert-success" role="alert">Ticket submitted</div>');
                  $('#userSubmitFormOuter').hide().html('');
                  $('#searchTarget').html('');
                } else {
                  that.showAppMessage('Saved ticket', 'success');
                  formBlock.html('<div class="alert alert-danger" role="alert">Internal error</div>');
                }

              },
              error: function(xhr, status, error) {
                var message = 'Ticket save failed';
                that.reportAjaxError(message, xhr, status, error);
                void(error);
                // FIXME handle error
                formBlock.html('<div class="alert alert-danger" role="alert">Ticket failed</div>');
              }
            }
          );
        });
      }

      /* Data format
       var data = {
       title: ticketTitle,
       songId: songId,
       band: currentBand,
       private: isPrivate, // n/a
       blocking: isBlocked // n/a
       };

       // see: url: '/api/saveTicket'
       // probably admin-only at the moment
       */

    },

    updatePerformanceStats: function() {
      var that = this;
      var performed = {};
      var lastByPerformer = {};
      var ticketOrdinal = 1;
      var ticketTime = null;

      var pad = function(number) {
        if (number < 10) {
          return '0' + number;
        }
        return number;
      };

      // First check number of songs performed before this one
      var sortContainer = $('.sortContainer');
      var lastSongDuration = null;
      var lastTicketNoSong = true;

      var nthUnused = 1;

      sortContainer.find('.ticket').each(function() {
        var realTime;
        var ticketId = $(this).data('ticket-id');
        var ticketData = $(this).data('ticket');

        if (ticketData.startTime) {
          realTime = new Date(ticketData.startTime * 1000);
        }

        $(this).removeClass('shown');

        if (!(ticketData.used || ticketData.private)) {
          if (nthUnused <= that.displayOptions.upcomingCount) {
            $(this).addClass('shown');
          }
          nthUnused++;
        }

        $(this).find('.ticketOrdinal').text('# ' + ticketOrdinal);
        // Fixme read ticketStart from data if present
        if (realTime) {
          ticketTime = realTime;
        } else if (ticketTime) {
          // If last song had an implicit time, add defaultSongOffsetMs to it and assume next song starts then
          // If this is in the past, assume it starts now!
          var songOffsetMs;
          if (lastTicketNoSong) {
            songOffsetMs = that.defaultSongIntervalSeconds * 1000;
            // Could just be a message, could be a reset / announcement, so treat as an interval only
          } else if (lastSongDuration) {
            songOffsetMs = (that.defaultSongIntervalSeconds + lastSongDuration) * 1000;
          } else {
            songOffsetMs = (that.defaultSongIntervalSeconds + that.defaultSongLengthSeconds) * 1000;
          }
          ticketTime = new Date(Math.max(ticketTime.getTime() + songOffsetMs, Date.now()));
        } else {
          ticketTime = new Date();
        }
        $(this).find('.ticketTime').text(pad(ticketTime.getHours()) + ':' + pad(ticketTime.getMinutes()));

        // Update performer stats (done/total)
        $(this).find('.performer').each(function() {
          var performerId = $(this).data('performer-id');
          var performerName = $(this).data('performer-name');
          if (!performed.hasOwnProperty(performerId)) {
            performed[performerId] = 0;
          }
          $(this).find('.songsDone').text(performed[performerId]);

          $(this).removeClass(
            function(i, oldClass) {
              void(i);
              var classes = oldClass.split(' ');
              var toRemove = [];
              for (var cIdx = 0; cIdx < classes.length; cIdx++) {
                if (classes[cIdx].match(/^performerDoneCount/)) {
                  toRemove.push(classes[cIdx]);
                }
              }
              return toRemove.join(' ');
            }
          ).addClass('performerDoneCount' + performed[performerId]);
          performed[performerId]++;

          // Now check proximity of last song by this performer
          if (lastByPerformer.hasOwnProperty(performerId)) {
            var distance = ticketOrdinal - lastByPerformer[performerId].idx;
            $(this).removeClass('proximityIssue');
            $(this).removeClass('proximityIssue1');
            if ((distance < 3) && (performerName.charAt(0) !== '?')) {
              $(this).addClass('proximityIssue');
              if (distance === 1) {
                $(this).addClass('proximityIssue1');
              }
            }
          } else {
            // Make sure they've not got a proximity marker on a ticket that's been dragged to top
            $(this).removeClass('proximityIssue');
          }
          lastByPerformer[performerId] = {idx: ticketOrdinal, ticketId: ticketId};
        });
        ticketOrdinal++;

        if (ticketData.song) {
          lastSongDuration = ticketData.song.duration;
          lastTicketNoSong = false;
        } else {
          lastSongDuration = 0;
          lastTicketNoSong = true;
        } // Set non-song ticket to minimum duration
      });

      // Then update all totals
      sortContainer.find('.performer').each(function() {
        var performerId = $(this).data('performer-id');
        var totalPerformed = performed[performerId];
        $(this).find('.songsTotal').text(totalPerformed);
      });
    },

    /**
     * Show a message in the defined appMessageTarget (f any)
     *
     * @param message {string} Message to show (replaces any other)
     * @param [className='info'] {string} 'info','success','warning','danger'
     */
    showAppMessage: function(message, className) {
      var that = this;
      if (this.messageTimer) {
        clearTimeout(this.messageTimer);
      }

      this.messageTimer = setTimeout(function() {
        that.appMessageTarget.html('');
      }, 5000);

      if (!className) {
        className = 'info';
      }
      if (this.appMessageTarget) {
        var block = $('<div />').addClass('alert alert-' + className);
        block.text(message);
        this.appMessageTarget.html('').append(block);
      }
    },

    ucFirst: function(string) {
      return string.charAt(0).toUpperCase() + string.slice(1);
    },

    reportAjaxError: function(message, xhr, status, error) {
      this.showAppMessage(
        this.ucFirst(status) + ': ' + message + ': ' + error + ', ' + xhr.responseJSON.error,
        'danger'
      );
    },

    checkRemoteRedirect: function() {
      window.setInterval(function() {
          $.get('/api/remotesRedirect', function(newPath) {
            if (newPath && (newPath !== window.location.pathname)) {
              window.location.pathname = newPath;
            }
          });
        },
        10000);
    },

    reloadPerformers: function(callback) {
      var that = this;
      $.get('/api/performers', function(performers) {
        that.performers = performers;
        if (callback) {
          callback();
        }
      });
    },


    /**
     * Return the instrument abbreviation played by a given performer name
     *
     * @param performerName
     * @param band
     * @returns {*}
     */
    findPerformerInstrument: function(performerName, band) {
      var instrumentPlayers;
      for (var instrumentCode in band) {
        if (band.hasOwnProperty(instrumentCode)) {
          instrumentPlayers = band[instrumentCode];
          for (var i = 0; i < instrumentPlayers.length; i++) {
            if (instrumentPlayers[i].toUpperCase() === performerName.toUpperCase()) {
              // X console.log(performerName + ' plays ' + instrumentCode);
              return instrumentCode;
            }
          }
        }
      }
      return null;
    },

    /**
     * Handle performer add / remove by performer button / text input
     *
     * @param band Object map of instrument: [performers]
     * @param instrument Instrument code
     * @param changedPerformer Performer to add or remove
     * @param isAdd If true, add performer, else remove
     */
    alterInstrumentPerformerList: function(band, instrument, changedPerformer, isAdd) {
      var that = this;
      var currentInstrumentPerformers = band[instrument];

      var newInstrumentPerformers = [];
      for (var i = 0; i < currentInstrumentPerformers.length; i++) {
        var member = currentInstrumentPerformers[i].trim(); // Trim only required when we draw data from manual input
        if (member.length) {
          if (member.toUpperCase() !== changedPerformer.toUpperCase()) {
            // If it's not the name on our button, no change
            newInstrumentPerformers.push(member);
          }
        }
      }

      if (isAdd) { // If we've just selected a new user, append them
        newInstrumentPerformers.push(changedPerformer);
        if (!that.performerExists(changedPerformer)) {
          that.addPerformerByName(changedPerformer);
        }
      }

      band[instrument] = newInstrumentPerformers;

    }
  };
}());