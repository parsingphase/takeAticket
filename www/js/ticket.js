/**
 * Created by wechsler on 04/08/15.
 */
var ticketer = {
    drawTemplate: null,
    manageTemplate: null,
    songAutocompleteItemTemplate: null,
    addTicketTemplate: null,
    songDetailsTemplate: null,

    displayOptions: {},

    performers: [],

    go: function () {
        this.initTemplates();

        ticketer.reloadTickets();
        setInterval(function () {
            ticketer.reloadTickets();
        }, 10000);
    },

    drawDisplayTicket: function (ticket) {
        return this.drawTemplate({ticket: ticket});
    },

    drawManageableTicket: function (ticket) {
        ticket.used = Number(ticket.used); // force int

        return this.manageTemplate({ticket: ticket});
    },

    reloadTickets: function () {
        var that = this;
        console.log('reloadTickets');

        $.get('/api/next', function (tickets) {

            var out = '';
            for (var i = 0; i < tickets.length; i++) {
                var ticket = tickets[i];
                out += that.drawDisplayTicket(ticket);
            }
            //out += Date.now().toLocaleString();

            var target = $('#target');
            target.html(out);

            target.find('.auto-font').each(
                function () {
                    var scale = 1.05 * Math.min(this.scrollWidth / this.clientWidth); // extra scale to fit neatly
                    var font = Number($(this).css('font-size').replace(/[^0-9]+$/, ''));
                    //console.log(['width', outerWidth, outerScroll, innerWidth, innerScroll, font]);
                    $(this).css('font-size', (font / scale) + 'px');
                }
            );

        });
    },

    enableButtons: function (topElement) {
        var that = this;

        $(topElement).find('.performButton').click(function () {
            that.performButtonCallback(this);
        });

        $(topElement).find('.removeButton').click(function () {
            that.removeButtonCallback(this);
        });
    },

    drawAddTicketForm: function () {
        $('.addTicketOuter').html(this.addTicketTemplate({performers: this.performers}));
    },

    enableSongSearchBox: function (songSearchInput, songSearchResultsTarget, songClickHandler) {
        var that = this;
        $(songSearchInput).keyup(
            function () {
                var songComplete = $(songSearchResultsTarget);
                var input = $(this);
                var searchString = input.val();
                //console.log('SS: ' + searchString);
                if (searchString.length >= 3) {
                    console.log('SS+: ' + searchString);
                    $.ajax({
                        method: 'POST',
                        data: {
                            searchString: searchString
                        },
                        url: '/api/songSearch',
                        success: function (data, status) {
                            console.log(['songSearch returned', data]);
                            var songs = data.songs;
                            if (input.val() == data.searchString) {
                                // ensure autocomplete response is still valid for current input value
                                var out = '';
                                for (var i = 0; i < songs.length; i++) {
                                    var song = songs[i];
                                    out += that.songAutocompleteItemTemplate({song: song});
                                }
                                songComplete.html(out).show();

                                // now attach whole song as data:
                                for (i = 0; i < songs.length; i++) {
                                    song = songs[i];
                                    var songId = song.id;
                                    songComplete.find('.acSong[data-song-id=' + songId + ']').data('song', song);
                                }

                                that.enableAcSongSelector(songComplete, songClickHandler);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.log('songSearch post ERROR: ' + status);
                            void(error);
                        }
                    });
                } else {
                    songComplete.html('');
                }
            }
        );
    },

    resetAddTicketBlock: function () {
        var that = this;

        this.drawAddTicketForm();

        $('.addTicketButton').click(function () {
            that.addTicketCallback();
        });

        var ticketTitleInput = $('.addTicketTitle');
        ticketTitleInput.keydown(function (e) {
            if (e.keyCode == 13) {
                //that.addTicketCallback();
                var bandName = $('.addTicketTitle').val();
                $('.selectedBand').text(bandName);
            }
        });

        $('.addPerformerButton').click(function () {
            var name = $(this).text();
            var selected = $(this).data('selected') ? 0 : 1; // reverse to get new state
            if (selected) {
                $(this).removeClass('btn-default').addClass('btn-primary');
            } else {
                $(this).removeClass('btn-primary').addClass('btn-default');
            }
            $(this).data('selected', selected); // toggle

            console.log('Clicked performer name: "' + name + '"');

            // split out band name and see if we need to add the new name
            var currentBandList = ticketTitleInput.val();
            console.log('Current band: "' + currentBandList + '"');
            var bandMembersRaw = currentBandList.split(/\s*,\s*/);
            var bandMembers = [];
            for (var i = 0; i < bandMembersRaw.length; i++) {
                var member = bandMembersRaw[i].trim();
                if (member.length) {
                    // don't add the member in the ticket (we'll add below if approriate)
                    if (member.toUpperCase() != name.toUpperCase()) {
                        bandMembers.push(member);
                    }
                }
            }
            if (selected) {
                bandMembers.push(name);
            }

            ticketTitleInput.val(bandMembers.sort().join(', '));
        });

        var songSearchInput = '.addSongTitle';
        var songSearchResultsTarget = '.songComplete';
        var songClickHandler = that.managePageSongSelectionClick;

        this.enableSongSearchBox(songSearchInput, songSearchResultsTarget, songClickHandler);
    },

    manage: function (tickets) {
        var that = this;
        this.initTemplates();
        //console.log(tickets);

        var out = '';
        for (var i = 0; i < tickets.length; i++) {
            var ticket = tickets[i];
            out += that.drawManageableTicket(ticket);
        }
        $('#target').html(out);

        var $sortContainer = $('.sortContainer');
        $sortContainer.sortable({
            axis: 'y',
            update: function (event, ui) {
                void(event);
                void(ui);
                that.ticketOrderChanged();
            }
        }).disableSelection().css('cursor', 'move');

        this.enableButtons($sortContainer);

        this.resetAddTicketBlock();

    },

    initSearchPage: function () {
        var that = this;
        this.initTemplates();
        this.enableSongSearchBox('.searchString', '.songComplete', that.searchPageSongSelectionClick);
    },

    initTemplates: function () {
        this.manageTemplate = Handlebars.compile(
            '<div class="ticket well {{#if ticket.used}}used{{/if}}" data-ticket-id="{{ ticket.id }}">' +
            '        <div class="pull-right">' +
            '        <button class="btn btn-primary performButton" data-ticket-id="{{ ticket.id }}">Performing</button>' +
            '        <button class="btn btn-danger removeButton" data-ticket-id="{{ ticket.id }}">Remove</button>' +
            '        </div>' +
            '        <div class="ticketId">#{{ ticket.id }}:</div> ' +
            '<div class="pendingSong">' +
            '<span class="fa fa-group"></span> ' +

                // Display performers with metadata if valid, else just the band title.
            '{{#if ticket.performers}}' +
            '{{#each ticket.performers}}' +
            '<span class="performer"> {{performerName}} ({{songsDone}}/{{songsPending}}) </span>' +
            '{{/each}}' +
            '{{else}}' +
            '{{ ticket.title }}' +
            '{{/if}}' +

            '{{#if ticket.used}} (done){{/if}}' +
            '{{#if ticket.song}}<br /><span class="fa fa-music"></span> {{ticket.song.artist}}: ' +
            '{{ticket.song.title}}' +
            '{{/if}}' +
            '</div>' +
            '</div>'
        );

        //var block = '<div class="ticket well"><p class="text-center">' + title + '</p></div>';

        //noinspection JSUnresolvedVariable
        this.drawTemplate = Handlebars.compile(
            '<div class="ticket well ' +
            (this.displayOptions.songInPreview ? 'withSong' : 'noSong') +
            '" data-ticket-id="{{ ticket.id }}">' +
            '        <div class="ticket-inner">' +
            '        <p class="text-center band auto-font">{{ticket.title}}</p>' +
            (this.displayOptions.songInPreview ? '{{#if ticket.song}}<p class="text-center song auto-font">{{ticket.song.artist}}: {{ticket.song.title}}</p>{{/if}}' : '') +
            '        </div>' +
            '</div>  '
        );

        this.songAutocompleteItemTemplate = Handlebars.compile(
            '<div class="acSong" data-song-id="{{ song.id }}">' +
            '        <div class="acSong-inner">' +
            '        {{song.artist}}: {{song.title}}' +
            '        </div>' +
            '</div>  '
        );

        this.addTicketTemplate = Handlebars.compile(
            '<div class="addTicket well">' +
            '<div class="pull-right">' +
            '<button class="addTicketButton btn btn-success">Add</button>' +
            '</div>' +

            '<h3>Add new ticket</h3>' +

            '<div>' +
            '<div class="addTicketSong">' +
            '<div class="selectedFieldOuter"><span class="fa fa-music fa-2x"></span>' +
            '<input type="hidden" class="selectedSongId"/> <span class="selectedSong"></span>' +
            '</div>' +
            '<div class="input-group input-group">' +
            '<span class="input-group-addon" id="search-addon1"><span class="fa fa-search"></span> </span>' +
            '<input class="addSongTitle form-control" placeholder="Search song or use code"/>' +
            '</div>' +

            '<div class="songCompleteOuter">' +
            '<div class="songComplete"></div>' +
            '</div>' +
            '</div>' +

            '<div class="addTicketBand">' +
            '<div class="selectedFieldOuter"><span class="fa fa-group fa-2x"></span>' +
            '<span class="selectedBand"></span>' +
            '</div>' +
            '<div class="input-group input-group">' +
            '<span class="input-group-addon" id="group-addon1"><span class="fa fa-plus"></span> </span>' +
            '<input class="addTicketTitle form-control" placeholder="Band members (comma-separated)"/>' +
            '</div>' +

            '<div class="performers">{{#if performers}}{{#each performers}}' +
            '<span class="btn btn-default addPerformerButton" data-selected="0">{{performerName}}</span>' +
            '{{/each}}{{/if}}</div>' +

            '</div>' +
            '</div>' +
            '</div>'
        );

        this.songDetailsTemplate = Handlebars.compile(
            '<div class="songDetails"><h3>{{song.artist}}: {{song.title}}</h3>' +
            'Code: {{song.codeNumber}}<br /> ' +
            'Harmony? {{#if song.hasHarmony}}Yes{{else}}No{{/if}}<br /> ' +
            'Keys? {{#if song.hasKeys}}Yes{{else}}No{{/if}}<br /> ' +
            'Source: {{song.source}}' +
            '</div>'
        );

    },

    ticketOrderChanged: function () {
        //console.log('Ticket order changed');
        var idOrder = [];
        $('#target').find('.ticket').each(
            function () {
                var ticketBlock = $(this);
                //console.log(ticketBlock.data());
                var ticketId = ticketBlock.data('ticketId');
                //console.log('Id: ' + ticketId);
                idOrder.push(ticketId);
            }
        );

        //console.log(['New order', idOrder]);

        $.ajax({
            method: 'POST',
            data: {
                idOrder: idOrder
            },
            url: '/api/newOrder',
            success: function (data, status) {
                console.log('ticketOrderChanged post OK: ' + status);
            },
            error: function (xhr, status, error) {
                console.log('ticketOrderChanged post ERROR: ' + status);
                void(error);
            }
        });
    },

    addTicketCallback: function () {
        var that = this;
        var titleInput = $('.addTicketTitle');
        var newTitle = titleInput.val();
        var songInput = $('.selectedSongId');
        var songId = songInput.val();
        console.log('addTicket: ' + newTitle);
        $.ajax({
                method: 'POST',
                data: {
                    title: newTitle,
                    songId: songId
                },
                url: '/api/newTicket',
                success: function (data, status) {
                    void(status);
                    titleInput.val('');
                    songInput.val('');
                    $('#target').append(that.drawManageableTicket(data.ticket));
                    var ticketId = data.ticket.id;
                    var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                    that.enableButtons(ticketBlock);

                    if (data.performers) {
                        that.performers = data.performers;
                    }

                    that.resetAddTicketBlock();

                },
                error: function (xhr, status, error) {
                    void(error);
                    console.log('addTicketTitle post ERROR: ' + status);
                }
            }
        );
    },

    performButtonCallback: function (button) {
        //var that = this;

        button = $(button);
        var ticketId = button.data('ticketId');
        console.log('performButtonCallback: ' + ticketId);
        $.ajax({
                method: 'POST',
                data: {
                    ticketId: ticketId
                },
                url: '/api/useTicket',
                success: function (data, status) {
                    void(data);
                    void(status);
                    var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                    ticketBlock.addClass('used');
                    ticketBlock.append(' (done)');
                },
                error: function (xhr, status, error) {
                    void(xhr);
                    void(error);
                    console.log('performButtonCallback post ERROR: ' + status);
                }
            }
        );
    },

    removeButtonCallback: function (button) {
        button = $(button);
        var ticketId = button.data('ticketId');
        console.log('removeButtonCallback: ' + ticketId);
        $.ajax({
                method: 'POST',
                data: {
                    ticketId: ticketId
                },
                url: '/api/deleteTicket',
                success: function (data, status) {
                    void(status);
                    var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                    ticketBlock.remove();
                },
                error: function (xhr, status, error) {
                    void(error);
                    console.log('removeButtonCallback post ERROR: ' + status);
                }
            }
        );
    },

    enableAcSongSelector: function (outerElement, songClickHandler) {
        var that = this;
        outerElement.find('.acSong').click(
            function () {
                // find & decorate clicked element
                outerElement.find('.acSong').removeClass('selected');
                $(this).addClass('selected');

                var song = $(this).data('song');
                songClickHandler.call(that, song); // run in 'that' context
            }
        )
    },

    managePageSongSelectionClick: function (song) {
        var selectedId = song.id;
        var selectedSong = song.artist + ': ' + song.title;

        // perform actions with selected song
        var addTicketBlock = $('.addTicket');
        addTicketBlock.find('input.selectedSongId').val(selectedId);
        addTicketBlock.find('.selectedSong').text(selectedSong);
        console.log(['selected song', selectedId, selectedSong]);
    },

    searchPageSongSelectionClick: function (song) {
        var target = $('#searchTarget');
        //this.dumpSongInfo(song);
        target.html(this.songDetailsTemplate({song: song}));
    },

    dumpSongInfo: function (song) {
        console.log(['song', song]);
    }
};