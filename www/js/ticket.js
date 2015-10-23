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
    searchCount: 10,
    instrumentOrder: ['V', 'G', 'B', 'D', 'K'],
    defaultSongLengthSeconds: 240,
    defaultSongIntervalSeconds: 120,

    /**
     * @var {{songInPreview,upcomingCount,iconMapHtml}}
     */
    displayOptions: {},

    /**
     * List of all performers (objects) who've signed up in this session
     */
    performers: [],

    performerExists: function(performerName) {
      for (var i = 0; i < this.performers.length; i++) {
        if (this.performers[i].performerName.toLowerCase() == performerName.toLowerCase()) {
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

    /**
     * Draw an "upcoming" ticket
     * @param ticket {{band}}
     * @returns {*}
     */
    drawDisplayTicket: function(ticket) {
      // Sort band into standard order
      var unsortedBand = ticket.band;
      var sortedBand = {};
      for (var i = 0; i < this.instrumentOrder.length; i++) {
        var instrument = this.instrumentOrder[i];
        if (unsortedBand.hasOwnProperty(instrument)) {
          sortedBand[instrument] = unsortedBand[instrument];
        }
      }
      ticket.band = sortedBand;
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

            var spaceUsedByText = (this.scrollWidth - fixedWidth);
            var spaceAvailableForText = (this.clientWidth - fixedWidth);
            var rawScale = Math.max(spaceUsedByText / spaceAvailableForText, 1);
            var scale = 1.05 * rawScale;

            // 1.05 extra scale to fit neatly, fixedWidth is non-scaling elements
            var font = Number($(this).css('font-size').replace(/[^0-9]+$/, ''));
            $(this).css('font-size', Number(font / scale).toFixed() + 'px');
          }
        );

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

      // Current panel state in function scope
      var selectedInstrument = 'V';
      var currentBand = {};

      var controlPanelOuter = $('.editTicketOuter');

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

      // Enable 'Add' button
      $('.editTicketButton').click(editTicketCallback);
      $('.cancelTicketButton').click(cancelTicketCallback);

      // Enable the instrument tabs
      var allInstrumentTabs = controlPanelOuter.find('.instrument');

      allInstrumentTabs.click(
        function() {
          selectedInstrument = $(this).data('instrumentShortcode');
          setActiveTab(selectedInstrument);
        }
      );

      var ticketTitleInput = $('.editTicketTitle');

      // Copy band name into summary area on Enter
      ticketTitleInput.keydown(function(e) {
        if (e.keyCode == 13) {
          updateBandSummary();
        }
      });

      $('.newPerformer').keydown(function(e) {
        if (e.keyCode == 13) {
          var newPerformerInput = $('.newPerformer');
          var newName = newPerformerInput.val();
          if (newName.trim().length) {
            alterInstrumentPerformerList(selectedInstrument, newName, true);
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
          if (that.instrumentOrder[i] == selectedInstrument) {
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
        updateInstrumentTabs();
        rebuildPerformerList(controlPanelOuter.find('.performers'));
        if (ticket && ticket.song) {
          applyNewSong(ticket.song);
        }
      }

      function findPerformerInstrument(name) {
        var instrumentPlayers;
        for (var instrumentCode in currentBand) {
          if (currentBand.hasOwnProperty(instrumentCode)) {
            instrumentPlayers = currentBand[instrumentCode];
            for (var i = 0; i < instrumentPlayers.length; i++) {
              if (instrumentPlayers[i].toUpperCase() == name.toUpperCase()) {
                return instrumentCode;
              }
            }
          }
        }
        return null;
      }

      /**
       * Rebuild list of performer buttons according to overall performers list
       * and which instruments they are assigned to
       */
      function rebuildPerformerList() {
        var newButton;
        var targetElement = controlPanelOuter.find('.performers');
        targetElement.text(''); // Remove existing list

        var lastInitial = '';
        var performerCount = that.performers.length;
        var letterSpan;
        for (var pIdx = 0; pIdx < performerCount; pIdx++) {
          var performerName = that.performers[pIdx].performerName;
          var performerInstrument = findPerformerInstrument(performerName);
          var isPerforming = performerInstrument ? 1 : 0;
          var initialLetter = performerName.charAt(0).toUpperCase();
          if (lastInitial !== initialLetter) {
            if (letterSpan) {
              targetElement.append(letterSpan);
            }
            letterSpan = $('<span class="letterSpan"></span>');
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

          alterInstrumentPerformerList(selectedInstrument, name, selected);
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
        var data = {
          title: ticketTitle,
          songId: songId,
          band: currentBand
        };

        if (currentTicket) {
          data.existingTicketId = currentTicket.id;
        }

        $.ajax({
            method: 'POST',
            data: data,
            url: '/api/saveTicket',
            success: function(data, status) {
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
              void(error);
              // FIXME handle error
            }
          }
        );
      }

      function cancelTicketCallback() {
        that.resetEditTicketBlock();
      }

      function getTabByInstrument(instrument) {
        return controlPanelOuter.find('.instrument[data-instrument-shortcode=' + instrument + ']');
      }

      function setActiveTab(selectedInstrument) {
        allInstrumentTabs.removeClass('instrumentSelected');
        var selectedTab = getTabByInstrument(selectedInstrument);
        selectedTab.addClass('instrumentSelected');
        rebuildPerformerList(); // Rebuild in current context
        return selectedTab;
      }

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

      function updateInstrumentTabs() {
        var performersSpan;
        var performerString;

        for (var iIdx = 0; iIdx < that.instrumentOrder.length; iIdx++) {
          var instrument = that.instrumentOrder[iIdx];

          performersSpan = controlPanelOuter.
            find('.instrument[data-instrument-shortcode=' + instrument + ']').
            find('.instrumentPerformer');

          performerString = currentBand[instrument].join(', ');
          if (!performerString) {
            performerString = '<i>Needed</i>';
          }
          performersSpan.html(performerString);
        }

        updateBandSummary();
      }

      /**
       * Handle performer add / remove by performer button / text input
       * @param instrument
       * @param changedPerformer
       * @param isAdd
       */
      function alterInstrumentPerformerList(instrument, changedPerformer, isAdd) {
        var currentInstrumentPerformers = currentBand[selectedInstrument];

        var newInstrumentPerformers = [];
        for (var i = 0; i < currentInstrumentPerformers.length; i++) {
          var member = currentInstrumentPerformers[i].trim(); // Trim only required when we draw data from manual input
          if (member.length) {
            if (member.toUpperCase() != changedPerformer.toUpperCase()) {
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

        currentBand[selectedInstrument] = newInstrumentPerformers;
        // Now update band with new performers of this instrument

        updateInstrumentTabs();
        rebuildPerformerList();

        if (newInstrumentPerformers.length) { // If we've a performer for this instrument, skip to next
          nextInstrumentTab();
        }

      }

      /**
       *
       * @param {{id, title, artist, hasKeys, hasHarmony}} song
       */
      function applyNewSong(song) {
        var selectedId = song.id;
        var selectedSong = song.artist + ': ' + song.title;

        // Perform actions with selected song
        var editTicketBlock = $('.editTicket');
        editTicketBlock.find('input.selectedSongId').val(selectedId);
        editTicketBlock.find('.selectedSong').text(selectedSong);
        var keysTab = controlPanelOuter.find('.instrumentKeys');
        if (song.hasKeys) {
          keysTab.removeClass('instrumentUnused');
        } else {
          keysTab.addClass('instrumentUnused');
          // Also uncheck any performer for instrument (allow use elsewhere)
          currentBand.K = [];
          keysTab.find('.instrumentPerformer').html('<i>Needed</i>');
          rebuildPerformerList();
        }
      }
    },

    manage: function(tickets) {
      var that = this;
      this.initTemplates();
      var ticket, ticketBlock; // For loop iterations

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
      // TODO work out how to use options to more closely mimic 'each'
      Handlebars.registerHelper('commalist', function(context, options) {
        var retList = [];

        for (var key in context) {
          if (context.hasOwnProperty(key)) {
            retList.push(options.fn({k: key, v: context[key]}));
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
        var all = [];
        if (song.inRb3) {
          all.push('RB3');
        }
        if (song.inRb4) {
          all.push('RB4');
        }
        return all.join(', ');
      });

      this.manageTemplate = Handlebars.compile(
        '<div class="ticket well well-sm {{#if ticket.used}}used{{/if}}' +
        ' {{#if ticket.song.inRb3}}rb3ticket{{/if}} {{#if ticket.song.inRb4}}rb4ticket{{/if}}"' +
        ' data-ticket-id="{{ ticket.id }}">' +
        '        <div class="pull-right">' +
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
        '<div class="pendingSong">' +
        '<span class="fa fa-group"></span> ' +

          // Display performers with metadata if valid, else just the band title.
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
        (this.displayOptions.title ? 'withTitle' : 'noTitle') +
        '" data-ticket-id="{{ ticket.id }}">' +
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
          '{{ticket.song.artist}}: {{ticket.song.title}}</p>{{/if}}' : ''
        ) +
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
        '<button class="editTicketButton btn btn-success">Save</button>' +
        '<button class="cancelTicketButton btn">Cancel</button>' +
        '</div>' +

        '{{# if ticket}}<h3>Edit ticket <span class="fa fa-ticket"></span> {{ticket.id}}</h3>' +
        '{{else}}<h3>Add new ticket</h3>{{/if}}' +

        '<div class="editTicketInner">' +
        '<div class="editTicketSong">' +
        '<div class="ticketAspectSummary"><span class="fa fa-music fa-2x" title="Song"></span>' +
        '<input type="hidden" class="selectedSongId"/> ' +
        '<span class="selectedSong">{{#if ticket}}{{#if ticket.song}}{{ticket.song.artist}}: ' +
        '{{ticket.song.title}}{{/if}}{{/if}}</span>' +
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
        '<input class="editTicketTitle form-control" placeholder="Band name (optional)"' +
        ' value="{{#if ticket}}{{ticket.title}}{{/if}}"/>' +
        '</div>' + // /input-group

        '<div class="bandControls">' +
        '<div class="bandTabsOuter">' +
        '<div class="instruments">' +
        ' <div class="instrument instrumentVocals instrumentSelected" data-instrument-shortcode="V">' +
        '  <div class="instrumentName">Vocals</div>' +
        '  <div class="instrumentPerformer"><i>Needed</i></div>' +
        ' </div>' +
        ' <div class="instrument instrumentGuitar" data-instrument-shortcode="G">' +
        '  <div class="instrumentName">Guitar</div>' +
        '  <div class="instrumentPerformer"><i>Needed</i></div>' +
        ' </div>' +
        ' <div class="instrument instrumentBass" data-instrument-shortcode="B">' +
        '  <div class="instrumentName">Bass</div>' +
        '  <div class="instrumentPerformer"><i>Needed</i></div>' +
        ' </div>' +
        ' <div class="instrument instrumentDrums" data-instrument-shortcode="D">' +
        '  <div class="instrumentName">Drums</div>' +
        '  <div class="instrumentPerformer"><i>Needed</i></div>' +
        ' </div>' +
        ' <div class="instrument instrumentKeys instrumentUnused" data-instrument-shortcode="K">' +
        '  <div class="instrumentName">Keyboard</div>' +
        '  <div class="instrumentPerformer"><i>Needed</i></div>' +
        ' </div>' +
        '</div>' + // /instruments
        '<div class="performerSelect">' +
        '<div class="input-group input-group">' +
        '<span class="input-group-addon" id="group-addon-performer"><span class="fa fa-plus"></span> </span>' +
        '<input class="newPerformer form-control" placeholder="New performer (Firstname Initial)"/>' +
        '</div>' +

        '<div class="performers"></div>' +
        '</div>' + // /performerSelect
        '</div>' + // /bandTabsOuter
        '</div>' + // /bandControls
        '</div>' + // /editTicketBandColumn
        '<div class="clearfix"></div>' + // Clear after editTicketBandColumn
        '</div>' + // /editTicketInner
        '</div>' // /editTicket
      );

      this.songDetailsTemplate = Handlebars.compile(
        '<div class="songDetails"><h3>{{song.artist}}: {{song.title}}</h3>' +
        '<table>' +
        '<tr><th>Duration</th><td>{{durationToMS song.duration}}</td></tr> ' +
        '<tr><th>Code</th><td>{{song.codeNumber}}</td></tr> ' +
        '<tr><th>Harmony? </th><td>{{#if song.hasHarmony}}Yes{{else}}No{{/if}}</td></tr> ' +
        '<tr><th>Keys?</th><td>{{#if song.hasKeys}}Yes{{else}}No{{/if}}</td></tr> ' +
        '<tr><th>Games</th><td>{{#if song.inRb3}}RB3{{/if}} {{#if song.inRb4}}RB4{{/if}}</td></tr> ' +
        '<tr><th>Source</th><td>{{song.source}}</td></tr> ' +
        '</table>' +
        '</div>'
      );

    },

    ticketOrderChanged: function() {
      var idOrder = [];
      $('#target').find('.ticket').each(
        function() {
          var ticketBlock = $(this);
          var ticketId = ticketBlock.data('ticketId');
          idOrder.push(ticketId);
        }
      );

      $.ajax({
        method: 'POST',
        data: {
          idOrder: idOrder
        },
        url: '/api/newOrder',
        success: function(data, status) {
          // FIXME check return status
        },
        error: function(xhr, status, error) {
          void(error);
        }
      });

      this.updatePerformanceStats();
    },

    performButtonCallback: function(button) {
      var that = this;

      button = $(button);
      var ticketId = button.data('ticketId');
      $.ajax({
          method: 'POST',
          data: {
            ticketId: ticketId
          },
          url: '/api/useTicket',
          success: function(data, status) {
            void(data);
            void(status);
            var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
            ticketBlock.addClass('used');
            // TicketBlock.append(' (done)');

            // Fixme receive updated ticket info from API
            var ticket = ticketBlock.data('ticket');
            ticket.startTime = Date.now() / 1000;
            ticketBlock.data('ticket', ticket);

            that.updatePerformanceStats();
          },
          error: function(xhr, status, error) {
            void(xhr);
            void(error);
          }
        }
      );
    },

    removeButtonCallback: function(button) {
      var that = this;
      button = $(button);
      var ticketId = button.data('ticketId');
      $.ajax({
          method: 'POST',
          data: {
            ticketId: ticketId
          },
          url: '/api/deleteTicket',
          success: function(data, status) {
            void(status);
            var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
            ticketBlock.remove();
            that.updatePerformanceStats();
          },
          error: function(xhr, status, error) {
            void(error);
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
      var target = $('#searchTarget');
      target.html(this.songDetailsTemplate({song: song}));
    },

    updatePerformanceStats: function() {
      var that = this;
      var performed = {};
      var lastByPerformer = {};
      var ticketOrdinal = 1;
      var ticketTime = null;
      var lastDuration = null;

      var pad = function(number) {
        if (number < 10) {
          return '0' + number;
        }
        return number;
      };
      var defaultSongOffsetMs = (that.defaultSongIntervalSeconds + that.defaultSongLengthSeconds) * 1000;

      // First check number of songs performed before this one
      var sortContainer = $('.sortContainer');
      var lastSongDuration = null;

      sortContainer.find('.ticket').each(function() {
        var realTime;
        var ticketId = $(this).data('ticket-id');
        var ticketData = $(this).data('ticket');

        if (ticketData.startTime) {
          realTime = new Date(ticketData.startTime * 1000);
        }

        $(this).find('.ticketOrdinal').text('# ' + ticketOrdinal);
        // Fixme read ticketStart from data if present
        if (realTime) {
          ticketTime = realTime;
        } else if (ticketTime) {
          // If last song had an implicit time, add defaultSongOffsetMs to it and assume next song starts then
          // If this is in the past, assume it starts now!
          var songOffsetMs = defaultSongOffsetMs;
          if (lastSongDuration) {
            songOffsetMs = (that.defaultSongIntervalSeconds + lastSongDuration) * 1000;
          }
          ticketTime = new Date(Math.max(ticketTime.getTime() + songOffsetMs, Date.now()));
        } else {
          ticketTime = new Date();
        }
        $(this).find('.ticketTime').text(pad(ticketTime.getHours()) + ':' + pad(ticketTime.getMinutes()));

        // Update performer stats (done/total)
        $(this).find('.performer').each(function() {
          var performerId = $(this).data('performer-id');
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
            if (distance < 3) {
              $(this).addClass('proximityIssue');
            } else {
              $(this).removeClass('proximityIssue');
            }
          } else {
            // Make sure they've not got a proximity marker on a ticket that's been dragged to top
            $(this).removeClass('proximityIssue');
          }
          lastByPerformer[performerId] = {idx: ticketOrdinal, ticketId: ticketId};
        });
        ticketOrdinal++;
        lastSongDuration = ticketData.song.duration;
      });

      // Then update all totals
      sortContainer.find('.performer').each(function() {
        var performerId = $(this).data('performer-id');
        var totalPerformed = performed[performerId];
        $(this).find('.songsTotal').text(totalPerformed);
      });
    }
  };
}());