(function($) {
    'use strict';
    
    const BewerberboerseApp = {
        applications: [],
        filteredApplications: [],
        displayedCount: 25,
        template: null,
        filterableFields: [],
        filters: {
            searchQuery: '',
            professionFilter: ''
        },
        
        init: function() {
            if (typeof bewerberboerseData === 'undefined') {
                console.error('bewerberboerseData is not defined');
                $('#bewerberboerse-app').html('<p class="error">Plugin configuration error</p>');
                return;
            }
            
            this.loadTemplate();
            this.initEventListeners();
        },
        
        loadTemplate: function() {
            const self = this;
            
            $.ajax({
                url: bewerberboerseData.apiUrl + '/templates',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': bewerberboerseData.nonce
                },
                success: function(data) {
                    if (data && data.length > 0) {
                        self.template = data[0];
                        
                        if (self.template.filterable_fields && self.template.fields) {
                            try {
                                const filterableFieldNames = JSON.parse(self.template.filterable_fields);
                                const allFields = JSON.parse(self.template.fields);
                                
                                self.filterableFields = filterableFieldNames.map(fieldName => {
                                    const fieldInfo = allFields.find(f => f.name === fieldName || f.field_id === fieldName);
                                    return {
                                        name: fieldName,
                                        type: fieldInfo ? fieldInfo.type : 'text'
                                    };
                                });
                            } catch(e) {
                                console.error('Error parsing filterable_fields:', e);
                                self.filterableFields = [];
                            }
                        } else {
                            self.filterableFields = [];
                        }
                    }
                    self.loadApplications();
                },
                error: function(xhr, status, error) {
                    console.error('Error loading template:', error);
                    self.loadApplications();
                }
            });
        },
        
        loadApplications: function() {
            const self = this;
            
            $.ajax({
                url: bewerberboerseData.apiUrl + '/applications',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': bewerberboerseData.nonce
                },
                success: function(data) {
                    console.log('Loaded applications:', data);
                    self.applications = data;
                    self.filteredApplications = data;
                    self.renderApplications();
                },
                error: function(xhr, status, error) {
                    console.error('Error loading applications:', error);
                    $('#bewerberboerse-app').html('<p class="error">Fehler beim Laden der Bewerbungen</p>');
                }
            });
        },
        
        initEventListeners: function() {
            const self = this;
            
            $(document).on('input', '.bewerberboerse-search', function() {
                self.filters.searchQuery = $(this).val();
                self.applyFilters();
            });
            
            $(document).on('change input', '.bewerberboerse-filter', function() {
                const filterName = $(this).data('filter');
                self.filters[filterName] = $(this).val();
                self.applyFilters();
            });
            
            $(document).on('change', '.bewerberboerse-filter-select', function() {
                const filterName = $(this).data('filter');
                const selectedValue = $(this).val();
                
                if ($(this).hasClass('bewerberboerse-language-select')) {
                    self.handleLanguageSelection(filterName, selectedValue, $(this));
                }
                
                self.filters[filterName] = selectedValue;
                self.applyFilters();
            });
            
            $(document).on('change', '.bewerberboerse-filter-checkboxes input[type="checkbox"]', function() {
                const filterName = $(this).closest('.bewerberboerse-filter-checkboxes').data('filter');
                const checkedValues = $(this).closest('.bewerberboerse-filter-checkboxes')
                    .find('input[type="checkbox"]:checked')
                    .map(function() { return $(this).val(); })
                    .get();
                self.filters[filterName] = checkedValues.length > 0 ? checkedValues : null;
                self.applyFilters();
            });
            
            $(document).on('change', '.bewerberboerse-language-level input[type="checkbox"]', function() {
                self.applyFilters();
            });
            
            $(document).on('click', '.bewerberboerse-load-more', function() {
                self.displayedCount += 25;
                self.renderApplications();
            });
            
            $(document).on('click', '.bewerberboerse-card', function() {
                const id = $(this).data('id');
                self.showModal(id);
            });
        },
        
        applyFilters: function() {
            const self = this;
            
            this.filteredApplications = this.applications.filter(function(app) {
                if (self.filters.searchQuery) {
                    const searchLower = self.filters.searchQuery.toLowerCase();
                    const desiredPosition = self.findFieldValue(app, 'desired_position') || self.findFieldValue(app, 'gewünschter_beruf') || '';
                    const positionValue = self.findFieldValue(app, 'position') || self.findFieldValue(app, 'beruf') || '';
                    const position = Array.isArray(positionValue) ? positionValue.join(' ') : (positionValue || '');
                    
                    const skillsValue = self.findFieldValue(app, 'faehigkeiten') || self.findFieldValue(app, 'fähigkeiten') || self.findFieldValue(app, 'skills') || null;
                    let hasSkillMatch = false;
                    if (skillsValue && Array.isArray(skillsValue) && skillsValue.length > 0) {
                        hasSkillMatch = skillsValue.some(skill => 
                            String(skill).toLowerCase().includes(searchLower)
                        );
                    }
                    
                    if (!desiredPosition.toLowerCase().includes(searchLower) && 
                        !position.toLowerCase().includes(searchLower) &&
                        !hasSkillMatch) {
                        return false;
                    }
                }
                
                if (self.filters.professionFilter) {
                    const professionLower = self.filters.professionFilter.toLowerCase();
                    const desiredPosition = self.findFieldValue(app, 'desired_position') || self.findFieldValue(app, 'gewünschter_beruf') || '';
                    
                    if (!desiredPosition.toLowerCase().includes(professionLower)) {
                        return false;
                    }
                }
                
                if (self.filterableFields && self.filterableFields.length > 0) {
                    for (let field of self.filterableFields) {
                        const fieldName = field.name || field;
                        const fieldType = field.type || 'text';
                        if (self.filters[fieldName]) {
                            const fieldValue = self.findFieldValue(app, fieldName) || '';
                            const filterValue = self.filters[fieldName];
                            
                            if (Array.isArray(filterValue)) {
                                const fieldValueArray = Array.isArray(fieldValue) ? fieldValue : [fieldValue];
                                const hasMatch = filterValue.some(fv => 
                                    fieldValueArray.some(fva => {
                                        if (typeof fva === 'object' && fva.language) {
                                            return String(fva.language).toLowerCase().includes(String(fv).toLowerCase());
                                        }
                                        return String(fva).toLowerCase().includes(String(fv).toLowerCase());
                                    })
                                );
                                if (!hasMatch) {
                                    return false;
                                }
                            } else if (filterValue !== null && filterValue !== undefined && filterValue !== '') {
                                if (Array.isArray(fieldValue)) {
                                    if (fieldType === 'sprachkenntnisse' || fieldType === 'languages') {
                                        const languageMatch = fieldValue.some(item => {
                                            if (typeof item === 'object' && item.language) {
                                                return String(item.language).toLowerCase().includes(String(filterValue).toLowerCase());
                                            }
                                            return String(item).toLowerCase().includes(String(filterValue).toLowerCase());
                                        });
                                        if (!languageMatch) {
                                            return false;
                                        }
                                        
                                        const checkedLevels = $('.bewerberboerse-language-level input[type="checkbox"]:checked')
                                            .filter(function() {
                                                return $(this).data('language') === filterValue;
                                            }).map(function() {
                                                return $(this).val();
                                            }).get();
                                        
                                        if (checkedLevels.length > 0) {
                                            const levelOrder = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
                                            const minLevelIndex = Math.min(...checkedLevels.map(level => levelOrder.indexOf(level)));
                                            const minLevel = levelOrder[minLevelIndex];
                                            
                                            const hasLevelMatch = fieldValue.some(item => {
                                                if (typeof item === 'object' && item.language && 
                                                    String(item.language).toLowerCase() === filterValue.toLowerCase() &&
                                                    item.level) {
                                                    const itemLevelIndex = levelOrder.indexOf(item.level);
                                                    return itemLevelIndex >= minLevelIndex;
                                                }
                                                return false;
                                            });
                                            
                                            if (!hasLevelMatch) {
                                                return false;
                                            }
                                        }
                                    } else {
                                        const hasMatch = fieldValue.some(item => 
                                            String(item).toLowerCase().includes(String(filterValue).toLowerCase())
                                        );
                                        if (!hasMatch) {
                                            return false;
                                        }
                                    }
                                } else {
                                    if (!String(fieldValue).toLowerCase().includes(String(filterValue).toLowerCase())) {
                                        return false;
                                    }
                                }
                            }
                        }
                    }
                }
                
                return true;
            });
            
            this.displayedCount = 25;
            this.renderResultsOnly();
        },
        
        renderApplications: function() {
            const displayed = this.filteredApplications.slice(0, this.displayedCount);
            const hasMore = this.filteredApplications.length > this.displayedCount;
            
            let html = this.renderSidebar();
            html += '<div class="bewerberboerse-content">';
            html += this.renderSearchBar();
            
            if (displayed.length > 0) {
                html += '<div class="bewerberboerse-results">';
                displayed.forEach(function(app, index) {
                    html += this.renderApplicationCard(app, index);
                }, this);
                html += '</div>';
                
                if (hasMore) {
                    html += '<div class="bewerberboerse-load-more-container">';
                    html += '<button class="bewerberboerse-load-more">Weitere Ergebnisse</button>';
                    html += '</div>';
                }
            } else {
                html += '<p class="bewerberboerse-no-results">Keine Bewerbungen gefunden</p>';
            }
            
            html += '</div>';
            
            $('#bewerberboerse-app').html(html);
        },
        
        renderResultsOnly: function() {
            const displayed = this.filteredApplications.slice(0, this.displayedCount);
            const hasMore = this.filteredApplications.length > this.displayedCount;
            
            $('.bewerberboerse-results, .bewerberboerse-load-more-container, .bewerberboerse-no-results').remove();
            
            let html = '';
            
            if (displayed.length > 0) {
                html += '<div class="bewerberboerse-results">';
                displayed.forEach(function(app, index) {
                    html += this.renderApplicationCard(app, index);
                }, this);
                html += '</div>';
                
                if (hasMore) {
                    html += '<div class="bewerberboerse-load-more-container">';
                    html += '<button class="bewerberboerse-load-more">Weitere Ergebnisse</button>';
                    html += '</div>';
                }
            } else {
                html += '<p class="bewerberboerse-no-results">Keine Bewerbungen gefunden</p>';
            }
            
            $('.bewerberboerse-search-bar').after(html);
        },
        
        renderSidebar: function() {
            let html = '<div class="bewerberboerse-sidebar">';
            html += '<h3>Filter</h3>';
            
            if (this.filterableFields && this.filterableFields.length > 0) {
                this.filterableFields.forEach(field => {
                    html += this.renderDynamicFilter(field);
                });
            }
            
            html += '</div>';
            return html;
        },
        
        renderDynamicFilter: function(field) {
            const fieldName = field.name || field;
            const fieldType = field.type || 'text';
            
            let html = '<div class="filter-group">';
            html += '<label>' + this.translateFieldKey(fieldName) + '</label>';
            
            if (fieldType === 'liste' || fieldType === 'dropdown' || fieldType === 'select') {
                const uniqueValues = this.getUniqueValuesForField(fieldName, fieldType);
                html += '<div class="bewerberboerse-filter-checkboxes" data-filter="' + fieldName + '">';
                uniqueValues.forEach(value => {
                    html += '<label><input type="checkbox" value="' + this.escapeHtml(String(value)) + '"> ' + this.escapeHtml(String(value)) + '</label>';
                });
                html += '</div>';
            } else if (fieldType === 'sprachkenntnisse' || fieldType === 'languages') {
                const uniqueValues = this.getUniqueValuesForField(fieldName, fieldType);
                html += '<select class="bewerberboerse-filter-select bewerberboerse-language-select" data-filter="' + fieldName + '">';
                html += '<option value="">Alle Sprachen</option>';
                uniqueValues.forEach(value => {
                    html += '<option value="' + this.escapeHtml(String(value)) + '">' + this.escapeHtml(String(value)) + '</option>';
                });
                html += '</select>';
                html += '<div class="bewerberboerse-language-levels" style="display: none;"></div>';
            } else if (fieldType === 'fuehrerschein' || fieldType === 'drivingLicense') {
                const uniqueValues = this.getUniqueValuesForField(fieldName, fieldType);
                html += '<div class="bewerberboerse-filter-checkboxes" data-filter="' + fieldName + '">';
                uniqueValues.forEach(value => {
                    html += '<label><input type="checkbox" value="' + this.escapeHtml(String(value)) + '"> ' + this.escapeHtml(String(value)) + '</label>';
                });
                html += '</div>';
            } else if (fieldType === 'arbeitszeit' || fieldType === 'workType') {
                const uniqueValues = this.getUniqueValuesForField(fieldName, fieldType);
                html += '<div class="bewerberboerse-filter-checkboxes" data-filter="' + fieldName + '">';
                uniqueValues.forEach(value => {
                    html += '<label><input type="checkbox" value="' + this.escapeHtml(String(value)) + '"> ' + this.escapeHtml(String(value)) + '</label>';
                });
                html += '</div>';
            } else if (fieldType === 'zahl' || fieldType === 'number') {
                html += '<input type="number" class="bewerberboerse-filter" data-filter="' + fieldName + '" placeholder="' + this.translateFieldKey(fieldName) + '">';
            } else {
                html += '<input type="text" class="bewerberboerse-filter" data-filter="' + fieldName + '" placeholder="' + this.translateFieldKey(fieldName) + '">';
            }
            
            html += '</div>';
            return html;
        },
        
        renderSearchBar: function() {
            let html = '<div class="bewerberboerse-search-bar">';
            html += '<input type="text" class="bewerberboerse-search" placeholder="Suche nach Beruf oder Fähigkeiten">';
            html += '<button class="bewerberboerse-search-btn">Suchen</button>';
            html += '</div>';
            return html;
        },
        
        renderApplicationCard: function(app, index) {
            const desiredPositions = this.getDesiredPositions(app);
            
            const location = app.ort || app.location || 'Deutschland';
            
            const workType = app.arbeitszeit || app.workType || 'Vollzeit';
            
            const availability = this.formatAvailability(
                this.findFieldValue(app, 'verfuegbarkeit') || 
                this.findFieldValue(app, 'verfügbarkeit') || 
                this.findFieldValue(app, 'availableFrom') || 
                'Ab sofort'
            );
            
            const workExperience = app.berufserfahrung || app.work_experience;
            const lastExperience = this.getLastExperience(workExperience);
            const experienceText = this.calculateExperience(workExperience);
            
            const education = app.bildung || app.education;
            const lastEducation = this.getLastEducation(education);
            
            let html = '<div class="bewerberboerse-card" data-id="' + app.id + '">';
            html += '<div class="bewerberboerse-card-content">';
            
            html += '<div class="bewerberboerse-card-col">';
            html += '<div class="bewerberboerse-card-position">' + this.escapeHtml(desiredPositions || '-') + '</div>';
            html += '</div>';
            
            html += '<div class="bewerberboerse-card-col">';
            if (lastExperience) {
                html += '<div class="bewerberboerse-card-experience">';
                html += '<div class="bewerberboerse-card-experience-title">';
                html += this.escapeHtml(lastExperience.position || '-');
                if (lastExperience.company) {
                    html += ', ' + this.escapeHtml(lastExperience.company);
                }
                if (experienceText) {
                    html += ', ' + experienceText + ' Jahre Berufserfahrung';
                }
                html += '</div>';
                html += '</div>';
            } else {
                html += '<div class="bewerberboerse-card-experience-empty">-</div>';
            }
            html += '</div>';
            
            html += '<div class="bewerberboerse-card-col">';
            if (lastEducation) {
                html += '<div class="bewerberboerse-card-education">';
                html += '<div class="bewerberboerse-card-education-title">';
                html += this.escapeHtml(lastEducation.degree || '-');
                if (lastEducation.institution) {
                    html += ', ' + this.escapeHtml(lastEducation.institution);
                }
                if (lastEducation.is_current === 1 || lastEducation.is_current === '1') {
                    html += ' (Aktuell)';
                } else if (lastEducation.end_date) {
                    html += ', ' + new Date(lastEducation.end_date).getFullYear();
                }
                html += '</div>';
                html += '</div>';
            } else {
                html += '<div class="bewerberboerse-card-education-empty">-</div>';
            }
            html += '</div>';
            
            html += '<div class="bewerberboerse-card-col">';
            html += '<div class="bewerberboerse-card-location">';
            html += '<div class="bewerberboerse-card-location-title">' + this.escapeHtml(location) + '</div>';
            html += '<div>' + this.escapeHtml(availability) + '</div>';
            html += '<div>' + this.escapeHtml(workType) + '</div>';
            if (app.radius) {
                html += '<div class="bewerberboerse-card-radius">(Umkreis: ' + this.escapeHtml(String(app.radius)) + ')</div>';
            }
            html += '</div>';
            html += '<div class="bewerberboerse-card-index">#' + (index + 1) + '</div>';
            html += '</div>';
            
            html += '</div>';
            html += '</div>';
            
            return html;
        },
        
        showModal: function(id) {
            const self = this;
            
            const searchId = String(id);
            
            const application = this.applications.find(app => 
                String(app.id) === searchId || 
                String(app.hash) === searchId ||
                app.id === id || 
                app.hash === id
            );
            
            if (!application) {
                console.error('Application not found:', id);
                console.log('Available applications:', this.applications.map(app => ({
                    id: app.id,
                    hash: app.hash,
                    type: typeof app.id
                })));
                return;
            }
            
            this.renderModal(application);
        },
        
        renderModal: function(app) {
            const self = this;
            
            console.log('Rendering modal for app:', app);
            console.log('Template:', this.template);
            
            let modal = $('#bewerberboerse-modal');
            if (modal.length === 0) {
                $('body').append('<div id="bewerberboerse-modal" class="bewerberboerse-modal-overlay"></div>');
                modal = $('#bewerberboerse-modal');
            }
            
            const filledData = JSON.parse(app.filled_data || '{}');
            const allData = Object.assign({}, app, filledData);
            
            console.log('All data:', allData);
            
            let html = '<div class="bewerberboerse-modal-content">';
            html += '<span class="bewerberboerse-modal-close">&times;</span>';
            
            html += '<div class="bewerberboerse-modal-header">';
            html += '<h2>Bewerbung Details</h2>';
            
            const updatedAt = app.updated_at || app.updatedAt;
            if (updatedAt) {
                const date = new Date(updatedAt);
                const formattedDate = date.toLocaleDateString('de-DE');
                html += '<div class="bewerberboerse-modal-date">' + this.escapeHtml(formattedDate) + '</div>';
            }
            
            html += '</div>';
            
            html += '<div class="bewerberboerse-modal-body">';
            
            if (this.template && this.template.fields) {
                try {
                    const templateFields = JSON.parse(this.template.fields);
                    console.log('Template fields:', templateFields);
                    
                    const hasExperience = this.findFieldValue(allData, 'berufserfahrung') || this.findFieldValue(allData, 'work_experience');
                    const hasEducation = this.findFieldValue(allData, 'bildung') || this.findFieldValue(allData, 'education');
                    
                    const position = this.findFieldValue(allData, 'position') || this.findFieldValue(allData, 'desired_position');
                    
                    if (position) {
                        html += '<div class="bewerberboerse-modal-section">';
                        html += '<h2 class="bewerberboerse-modal-position-title">Beruf:</h2>';
                        html += '<div class="bewerberboerse-modal-position-value">';
                        
                        if (Array.isArray(position)) {
                            const positionText = position.map(p => {
                                if (typeof p === 'object' && p !== null) {
                                    return p.position || p.name || p.title || String(p);
                                }
                                return String(p);
                            }).join('   |   ');
                            html += this.escapeHtml(positionText);
                        } else {
                            html += this.escapeHtml(String(position));
                        }
                        
                        html += '</div>';
                        html += '</div>';
                    }
                    
                    const availability = this.findFieldValue(allData, 'verfuegbarkeit') || this.findFieldValue(allData, 'verfügbarkeit') || this.findFieldValue(allData, 'availableFrom');
                    const workType = this.findFieldValue(allData, 'arbeitszeit') || this.findFieldValue(allData, 'workType');
                    const location = this.findFieldValue(allData, 'ort') || this.findFieldValue(allData, 'location');
                    
                    if (availability || workType || location) {
                        html += '<div class="bewerberboerse-modal-section">';
                        html += '<div class="bewerberboerse-modal-info-grid">';
                        
                        if (availability) {
                            html += '<div class="bewerberboerse-modal-info-item">';
                            html += '<span class="bewerberboerse-modal-info-icon">📅</span>';
                            html += '<div>';
                            html += '<div class="bewerberboerse-modal-info-label">Verfügbarkeit</div>';
                            html += '<div class="bewerberboerse-modal-info-value">' + this.escapeHtml(this.formatAvailability(availability)) + '</div>';
                            html += '</div>';
                            html += '</div>';
                        }
                        
                        if (workType) {
                            html += '<div class="bewerberboerse-modal-info-item">';
                            html += '<span class="bewerberboerse-modal-info-icon">💼</span>';
                            html += '<div>';
                            html += '<div class="bewerberboerse-modal-info-label">Arbeitszeit</div>';
                            html += '<div class="bewerberboerse-modal-info-value">' + this.escapeHtml(String(workType)) + '</div>';
                            html += '</div>';
                            html += '</div>';
                        }
                        
                        if (location) {
                            html += '<div class="bewerberboerse-modal-info-item">';
                            html += '<span class="bewerberboerse-modal-info-icon">📍</span>';
                            html += '<div>';
                            html += '<div class="bewerberboerse-modal-info-label">Standort</div>';
                            html += '<div class="bewerberboerse-modal-info-value">' + this.escapeHtml(String(location));
                            const radius = this.findFieldValue(allData, 'radius');
                            if (radius) {
                                html += ' <span class="bewerberboerse-modal-radius">(Umkreis: ' + this.escapeHtml(String(radius)) + ' km)</span>';
                            }
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                        }
                        
                        html += '</div>';
                        html += '</div>';
                    }
                    
                    if (hasExperience || hasEducation) {
                        html += '<div class="bewerberboerse-modal-section">';
                        html += '<h3>Lebenslauf</h3>';
                        html += '<div class="bewerberboerse-modal-experience-education">';
                        
                        if (hasExperience) {
                            html += this.renderExperienceEducation('left', hasExperience, 'Berufserfahrung');
                        }
                        
                        if (hasEducation) {
                            html += this.renderExperienceEducation('right', hasEducation, 'Bildung');
                        }
                        
                        html += '</div>';
                        html += '</div>';
                    }
                    
                    const excludedKeys = [
                        'id', 'hash', 'user_id', 'template_id', 'created_at', 'updated_at', 'is_active', 'isActive', 'createdAt', 'updatedAt',
                        'berufserfahrung', 'bildung', 'work_experience', 'education',
                        'position', 'desired_position', 'professions', 'wordpress_application_id',
                        'verfuegbarkeit', 'verügbarkeit', 'verfügbarkeit', 'availableFrom', 'arbeitszeit', 'workType', 'ort', 'location', 'radius',
                        'filled_data'
                    ];
                    
                    const templateFieldNames = templateFields.map(field => {
                        if (typeof field === 'object' && field !== null) {
                            return field.name || field.field_id || field.id;
                        }
                        return field;
                    }).filter(name => name);
                    
                    const dynamicFields = Object.keys(allData).filter(key => {
                        if (excludedKeys.includes(key)) {
                            return false;
                        }
                        
                        const fieldInTemplate = templateFieldNames.some(templateFieldName => {
                            if (typeof templateFieldName === 'string') {
                                return key.toLowerCase() === templateFieldName.toLowerCase() ||
                                       key.toLowerCase().replace(/ü/g, 'ue').replace(/ö/g, 'oe').replace(/ä/g, 'ae') === templateFieldName.toLowerCase() ||
                                       key.toLowerCase().replace(/ü/g, 'u').replace(/ö/g, 'o').replace(/ä/g, 'a') === templateFieldName.toLowerCase();
                            }
                            return false;
                        });
                        
                        if (!fieldInTemplate) {
                            return false;
                        }
                        
                        const value = allData[key];
                        if (value === null || value === undefined || value === '') {
                            return false;
                        }
                        
                        if (Array.isArray(value) && value.length === 0) {
                            return false;
                        }
                        
                        if (Array.isArray(value)) {
                            const hasNonEmpty = value.some(item => {
                                if (item === null || item === '' || item === false || item === undefined) {
                                    return false;
                                }
                                if (typeof item === 'object' && Object.keys(item).length === 0) {
                                    return false;
                                }
                                return true;
                            });
                            if (!hasNonEmpty) {
                                return false;
                            }
                        }
                        
                        const fieldConfig = templateFields.find(field => {
                            if (typeof field === 'object' && field !== null) {
                                const fieldName = field.name || field.field_id || field.id;
                                if (fieldName) {
                                    return key.toLowerCase() === fieldName.toLowerCase() ||
                                           key.toLowerCase().replace(/ü/g, 'ue').replace(/ö/g, 'oe').replace(/ä/g, 'ae') === fieldName.toLowerCase() ||
                                           key.toLowerCase().replace(/ü/g, 'u').replace(/ö/g, 'o').replace(/ä/g, 'a') === fieldName.toLowerCase();
                                }
                            }
                            return false;
                        });
                        
                        if (fieldConfig && fieldConfig.default !== undefined) {
                            const defaultValue = fieldConfig.default;
                            if (value === defaultValue || (typeof value === 'string' && value.trim() === String(defaultValue).trim())) {
                                return false;
                            }
                        }
                        
                        return true;
                    });
                    
                    if (dynamicFields.length > 0) {
                        html += '<div class="bewerberboerse-modal-section">';
                        html += '<h3>Bewerber/in im Detail</h3>';
                        html += '<div class="bewerberboerse-modal-fields">';
                        
                        dynamicFields.forEach(key => {
                            const value = allData[key];
                            html += this.renderModalField(key, value);
                        });
                        
                        html += '</div>';
                        html += '</div>';
                    }
                } catch(e) {
                    console.error('Error parsing template fields:', e);
                    html += this.renderModalFallback(allData);
                }
            } else {
                console.log('No template available, using fallback');
                html += this.renderModalFallback(allData);
            }

            html += '<div class="bewerberboerse-modal-section bewerberboerse-modal-contact-section">';
            html += '<button class="bewerberboerse-modal-contact-btn" data-hash="' + this.escapeHtml(app.hash) + '">Kontaktieren</button>';
            html += '<div class="bewerberboerse-modal-hash">' + this.escapeHtml(app.hash.toUpperCase()) + '</div>';
            html += '</div>';
            
            html += '</div>';
            html += '</div>';

            modal.html(html);
            modal.show();

            modal.find('.bewerberboerse-modal-close').on('click', function() {
                modal.hide();
            });

            modal.on('click', function(e) {
                if ($(e.target).hasClass('bewerberboerse-modal-overlay')) {
                    modal.hide();
                }
            });
            
            modal.find('.bewerberboerse-modal-contact-btn').on('click', function(e) {
                e.stopPropagation();
                const hash = $(this).data('hash');
                self.showContactModal(hash);
            });
        },
        
        renderExperienceEducation: function(position, data, title) {
            if (!data || !Array.isArray(data)) return '';
            
            const sortedData = [...data].sort((a, b) => {
                const dateA = a.end_date || (a.is_current === 1 ? new Date() : a.start_date) || '';
                const dateB = b.end_date || (b.is_current === 1 ? new Date() : b.start_date) || '';
                return new Date(dateB).getTime() - new Date(dateA).getTime();
            });
            
            const displayedCount = 3;
            const showMore = sortedData.length > displayedCount;
            const displayData = showMore ? sortedData.slice(0, displayedCount) : sortedData;
            
            let html = '<div class="bewerberboerse-modal-ex-ed-' + position + '">';
            html += '<h4 class="bewerberboerse-modal-ex-ed-title">' + title + '</h4>';
            html += '<div class="bewerberboerse-modal-ex-ed-list">';
            
            displayData.forEach((item, index) => {
                html += '<div class="bewerberboerse-modal-ex-ed-item">';
                
                const positionText = item.position || item.job_title || item.degree || '-';
                html += '<div class="bewerberboerse-modal-ex-ed-position">' + this.escapeHtml(positionText) + '</div>';
                
                if (item.company || item.institution) {
                    const companyText = item.company || item.institution;
                    html += '<div class="bewerberboerse-modal-ex-ed-company">' + this.escapeHtml(companyText) + '</div>';
                }
                
                html += '<div class="bewerberboerse-modal-ex-ed-dates">';
                if (item.start_date && item.end_date) {
                    html += this.escapeHtml(item.start_date + ' - ' + item.end_date);
                } else if (item.start_date) {
                    html += 'Seit ' + this.escapeHtml(item.start_date);
                }
                if (item.is_current === 1 || item.is_current === '1') {
                    html += ' <span class="bewerberboerse-modal-ex-ed-current">(Aktuell)</span>';
                }
                html += '</div>';
                
                html += '</div>';
            });
            
            if (showMore) {
                html += '<button class="bewerberboerse-modal-show-more" data-type="' + title.toLowerCase() + '">';
                html += '+' + (sortedData.length - displayedCount) + ' weitere anzeigen';
                html += '</button>';
            }
            
            html += '</div>';
            html += '</div>';
            
            return html;
        },
        
        renderLanguagesOrDrivingLicense: function(data) {
            if (!data || !Array.isArray(data)) return '';
            
            let html = '<div class="bewerberboerse-modal-languages-driving">';
            
            data.forEach((item, index) => {
                html += '<div class="bewerberboerse-modal-languages-driving-item">';
                
                if (typeof item === 'object' && item !== null) {
                    const language = item.language || '-';
                    const level = item.level || '';
                    html += '<div class="bewerberboerse-modal-languages-driving-name">' + this.escapeHtml(language);
                    if (level) {
                        html += ' <span class="bewerberboerse-modal-languages-driving-level">(' + this.escapeHtml(level) + ')</span>';
                    }
                    html += '</div>';
                } 
                else {
                    html += '<div class="bewerberboerse-modal-languages-driving-name">' + this.escapeHtml(String(item)) + '</div>';
                }
                
                html += '</div>';
            });
            
            html += '</div>';
            return html;
        },
        
        renderSkills: function(skills) {
            if (!skills) return '';
            
            let html = '<div class="bewerberboerse-modal-skills">';
            
            if (Array.isArray(skills)) {
                skills.forEach((skill, index) => {
                    if (skill) {
                        html += '<span class="bewerberboerse-modal-skill-badge">' + this.escapeHtml(String(skill)) + '</span>';
                    }
                });
            } 
            else if (typeof skills === 'string') {
                const skillList = skills.split(',').map(s => s.trim());
                skillList.forEach((skill, index) => {
                    if (skill) {
                        html += '<span class="bewerberboerse-modal-skill-badge">' + this.escapeHtml(skill) + '</span>';
                    }
                });
            }
            
            html += '</div>';
            return html;
        },
        
        renderModalField: function(key, value) {
            const escapedKey = this.escapeHtml(this.translateFieldKey(key));
            let html = '<div class="bewerberboerse-modal-field">';
            html += '<div class="bewerberboerse-modal-field-header">';
            html += '<h4 class="bewerberboerse-modal-field-title">' + escapedKey + '</h4>';
            html += '</div>';
            html += '<div class="bewerberboerse-modal-field-content">';
            
            const specialFields = ['sprachkenntnisse', 'languages', 'fuehrerschein', 'drivingLicense', 'faehigkeiten', 'skills'];
            const isSpecialField = specialFields.some(field => 
                key.toLowerCase() === field.toLowerCase() || 
                key.toLowerCase().replace(/ü/g, 'ue').replace(/ö/g, 'oe').replace(/ä/g, 'ae') === field.toLowerCase() ||
                key.toLowerCase().replace(/ü/g, 'u').replace(/ö/g, 'o').replace(/ä/g, 'a') === field.toLowerCase()
            );
            
            if (isSpecialField && Array.isArray(value) && value.length > 0) {
                if (key.toLowerCase().includes('sprach') || key.toLowerCase().includes('language') || 
                    key.toLowerCase().includes('fuehrerschein') || key.toLowerCase().includes('driving')) {
                    html += this.renderLanguagesOrDrivingLicense(value);
                }
                else if (key.toLowerCase().includes('fahig') || key.toLowerCase().includes('skill')) {
                    html += this.renderSkills(value);
                }
            }
            else if (Array.isArray(value)) {
                const isStringArray = value.length > 0 && value.every(item => typeof item === 'string');
                
                if (isStringArray) {
                    html += this.renderLanguagesOrDrivingLicense(value);
                } else {
                    html += '<div class="bewerberboerse-modal-field-array">';
                    value.forEach((item, index) => {
                        if (typeof item === 'string' && item.includes(' ')) {
                            html += '<div class="bewerberboerse-modal-field-skills">';
                            item.split(' ').forEach(skill => {
                                if (skill.trim()) {
                                    html += '<span class="bewerberboerse-modal-field-skill">' + this.escapeHtml(skill.trim()) + '</span>';
                                }
                            });
                            html += '</div>';
                        }
                        else if (typeof item === 'object' && item !== null) {
                            html += '<div class="bewerberboerse-modal-field-object">';
                            Object.keys(item).forEach(subKey => {
                                const subValue = item[subKey];
                                if (subValue !== null && subValue !== undefined && subValue !== '') {
                                    html += '<div class="bewerberboerse-modal-field-object-item">';
                                    html += '<strong>' + this.escapeHtml(subKey) + ':</strong> ' + this.escapeHtml(String(subValue));
                                    html += '</div>';
                                }
                            });
                            html += '</div>';
                        }
                        else {
                            html += '<div class="bewerberboerse-modal-field-array-item">' + this.escapeHtml(String(item)) + '</div>';
                        }
                    });
                    html += '</div>';
                }
            } else if (typeof value === 'object' && value !== null) {
                html += '<div class="bewerberboerse-modal-field-object">';
                Object.keys(value).forEach(subKey => {
                    const subValue = value[subKey];
                    if (subValue !== null && subValue !== undefined && subValue !== '') {
                        html += '<div class="bewerberboerse-modal-field-object-item">';
                        html += '<strong>' + this.escapeHtml(subKey) + ':</strong> ' + this.escapeHtml(String(subValue));
                        html += '</div>';
                    }
                });
                html += '</div>';
            } else {
                html += '<div class="bewerberboerse-modal-field-simple">' + this.escapeHtml(String(value)) + '</div>';
            }
            
            html += '</div>';
            html += '</div>';
            
            return html;
        },
        
        translateFieldKey: function(key) {
            const translations = {
                'full_name': 'Vollständiger Name',
                'email': 'E-Mail',
                'phone': 'Telefon',
                'position': 'Beruf',
                'desired_position': 'Gewünschter Beruf',
                'professions': 'Berufe',
                'education': 'Bildung',
                'work_experience': 'Berufserfahrung',
                'skills': 'Fähigkeiten',
                'faehigkeiten': 'Fähigkeiten',
                'languages': 'Sprachen',
                'sprachkenntnisse': 'Sprachkenntnisse',
                'drivingLicense': 'Führerschein',
                'fuehrerschein': 'Führerschein',
                'ort': 'Ort',
                'arbeitszeit': 'Arbeitszeit',
                'verfuegbarkeit': 'Verfügbarkeit',
                'verügbarkeit': 'Verfügbarkeit',
                'verfügbarkeit': 'Verfügbarkeit'
            };
            
            return translations[key] || key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        getUniqueValuesForField: function(fieldName, fieldType) {
            const uniqueValues = new Set();
            
            this.applications.forEach((app, index) => {
                const fieldValue = this.findFieldValue(app, fieldName);
                
                if (fieldValue !== null && fieldValue !== undefined && fieldValue !== '') {
                    if (Array.isArray(fieldValue)) {
                        fieldValue.forEach(item => {
                            if (item) {
                                if (typeof item === 'object' && item.language) {
                                    uniqueValues.add(String(item.language));
                                } else {
                                    uniqueValues.add(String(item));
                                }
                            }
                        });
                    } else {
                        uniqueValues.add(String(fieldValue));
                    }
                }
            });
            
            const result = Array.from(uniqueValues).sort();
            return result;
        },
        
        handleLanguageSelection: function(fieldName, selectedLanguage, $select) {
            const self = this;
            const levelsContainer = $select.next('.bewerberboerse-language-levels');
            
            if (!selectedLanguage) {
                levelsContainer.hide().empty();
                return;
            }
            
            const levels = this.getLanguageLevels(fieldName, selectedLanguage);
            
            let html = '<div class="bewerberboerse-language-level">';
            html += '<label>Mindestens:</label>';
            const levelOrder = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
            levelOrder.forEach(level => {
                if (levels.includes(level)) {
                    html += '<label><input type="checkbox" value="' + level + '" data-language="' + this.escapeHtml(selectedLanguage) + '"> ' + level + '</label>';
                }
            });
            html += '</div>';
            
            levelsContainer.html(html).show();
        },
        
        // Получить все уровни для конкретного языка
        getLanguageLevels: function(fieldName, language) {
            const levels = new Set();
            
            this.applications.forEach(app => {
                const fieldValue = this.findFieldValue(app, fieldName);
                if (Array.isArray(fieldValue)) {
                    fieldValue.forEach(item => {
                        if (typeof item === 'object' && item.language && 
                            String(item.language).toLowerCase() === language.toLowerCase()) {
                            if (item.level) {
                                levels.add(String(item.level));
                            }
                        }
                    });
                }
            });
            
            return Array.from(levels);
        },
        
        getDesiredPositions: function(app) {
            if (app.position) {
                if (Array.isArray(app.position)) {
                    return app.position.map(p => {
                        if (typeof p === 'object' && p !== null) {
                            return p.position || p.name || p.title || String(p);
                        }
                        return String(p);
                    }).join(' | ');
                }
                return String(app.position);
            }
            
            if (app.desired_position) {
                return app.desired_position;
            }
            
            if (app.positions && Array.isArray(app.positions) && app.positions.length > 0) {
                return app.positions.map(p => typeof p === 'object' ? p.position : p).join(' | ');
            }
            
            if (app.professions && Array.isArray(app.professions) && app.professions.length > 0) {
                return app.professions.join(' | ');
            }
            
            return '';
        },
        
        calculateExperience: function(workExperience) {
            if (!workExperience || workExperience.length === 0) return '';
            
            let totalMonths = 0;
            workExperience.forEach(exp => {
                if (!exp.start_date) return;
                
                const startDate = new Date(exp.start_date);
                const endDate = exp.end_date ? new Date(exp.end_date) : (exp.is_current === 1 ? new Date() : new Date());
                const months = (endDate.getFullYear() - startDate.getFullYear()) * 12 + (endDate.getMonth() - startDate.getMonth());
                totalMonths += months;
            });
            
            const years = Math.floor(totalMonths / 12);
            return years > 0 ? years.toString() : '0';
        },
        
        getLastExperience: function(workExperience) {
            if (!workExperience || workExperience.length === 0) return null;
            
            const sorted = [...workExperience].sort((a, b) => {
                const dateA = a.end_date ? new Date(a.end_date) : (a.is_current === 1 ? new Date() : new Date(a.start_date));
                const dateB = b.end_date ? new Date(b.end_date) : (b.is_current === 1 ? new Date() : new Date(b.start_date));
                return dateB.getTime() - dateA.getTime();
            });
            
            return sorted[0];
        },
        
        getLastEducation: function(education) {
            if (!education || education.length === 0) return null;
            
            const sorted = [...education].sort((a, b) => {
                const aIsCurrent = a.is_current === 1 || a.is_current === '1';
                const bIsCurrent = b.is_current === 1 || b.is_current === '1';
                
                if (aIsCurrent && !bIsCurrent) return 1;
                if (!aIsCurrent && bIsCurrent) return -1;
                
                const dateA = a.end_date ? new Date(a.end_date) : 
                             aIsCurrent ? new Date() : 
                             new Date(a.start_date || new Date());
                const dateB = b.end_date ? new Date(b.end_date) : 
                             bIsCurrent ? new Date() : 
                             new Date(b.start_date || new Date());
                
                return dateB.getTime() - dateA.getTime();
            });
            
            return sorted.find(edu => 
                !(edu.is_current === 1 || edu.is_current === '1')
            ) || sorted[0];
        },
        
        formatAvailability: function(availability) {
            if (!availability) return 'Ab sofort';
            
            if (availability === 'Ab sofort' || availability === 'ab sofort') {
                return 'Ab sofort';
            }
            
            const date = new Date(availability);
            if (!isNaN(date.getTime())) {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                const compareDate = new Date(date);
                compareDate.setHours(0, 0, 0, 0);
                
                if (compareDate.getTime() <= today.getTime()) {
                    return 'Ab sofort';
                }
                
                const formattedDate = date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
                return 'Ab ' + formattedDate;
            }
            
            return availability;
        },
        
        findFieldValue: function(data, fieldName) {
            if (data[fieldName] !== undefined) {
                return data[fieldName];
            }
            
            for (const key in data) {
                let normalizedKey = key.toLowerCase().replace(/ü/g, 'ue').replace(/ö/g, 'oe').replace(/ä/g, 'ae').replace(/ß/g, 'ss');
                let normalizedField = fieldName.toLowerCase().replace(/ü/g, 'ue').replace(/ö/g, 'oe').replace(/ä/g, 'ae').replace(/ß/g, 'ss');
                
                if (normalizedKey === normalizedField) {
                    return data[key];
                }
                
                normalizedKey = key.toLowerCase().replace(/ü/g, 'u').replace(/ö/g, 'o').replace(/ä/g, 'a').replace(/ß/g, 'ss');
                normalizedField = fieldName.toLowerCase().replace(/ü/g, 'u').replace(/ö/g, 'o').replace(/ä/g, 'a').replace(/ß/g, 'ss');
                
                if (normalizedKey === normalizedField) {
                    return data[key];
                }
            }
            
            return null;
        },
        
        groupFieldsByType: function(templateFields) {
            const groups = {};
            
            templateFields.forEach(field => {
                const fieldName = field.name || field.field_id;
                const fieldType = field.type || 'text';
                
                let groupName = 'other';
                switch(fieldType) {
                    case 'position':
                        groupName = 'position';
                        break;
                    case 'berufserfahrung':
                    case 'work_experience':
                        groupName = 'berufserfahrung';
                        break;
                    case 'bildung':
                    case 'education':
                        groupName = 'bildung';
                        break;
                    case 'sprachkenntnisse':
                    case 'languages':
                        groupName = 'sprachkenntnisse';
                        break;
                    case 'faehigkeiten':
                    case 'skills':
                        groupName = 'faehigkeiten';
                        break;
                    case 'fuehrerschein':
                    case 'drivingLicense':
                        groupName = 'fuehrerschein';
                        break;
                    default:
                        groupName = 'other';
                }
                
                if (!groups[groupName]) {
                    groups[groupName] = [];
                }
                groups[groupName].push(field);
            });
            
            return groups;
        },
        
        renderModalFallback: function(allData) {
            let html = '';
            
            const excludedKeys = [
                'id', 'hash', 'user_id', 'template_id', 'created_at', 'updated_at', 'is_active', 'isActive', 'createdAt', 'updatedAt',
                'berufserfahrung', 'bildung', 'sprachkenntnisse', 'fuehrerschein',
                'faehigkeiten', 'work_experience', 'education', 'skills', 'languages',
                'drivingLicense', 'position', 'desired_position', 'professions', 'wordpress_application_id'
            ];

            const dynamicFields = Object.keys(allData).filter(key =>
                !excludedKeys.includes(key) &&
                allData[key] !== null &&
                allData[key] !== undefined &&
                allData[key] !== ''
            );

            if (dynamicFields.length > 0) {
                html += '<div class="bewerberboerse-modal-section">';
                html += '<h3>Bewerber/in im Detail</h3>';
                html += '<div class="bewerberboerse-modal-fields">';

                dynamicFields.forEach(key => {
                    const value = allData[key];
                    html += this.renderModalField(key, value);
                });

                html += '</div>';
                html += '</div>';
            }
            
            return html;
        },
        
        showContactModal: function(hash) {
            const self = this;
            
            let contactModal = $('#bewerberboerse-contact-modal');
            if (contactModal.length === 0) {
                $('body').append('<div id="bewerberboerse-contact-modal" class="bewerberboerse-modal-overlay"></div>');
                contactModal = $('#bewerberboerse-contact-modal');
            }
            
            let html = '<div class="bewerberboerse-modal-content">';
            html += '<span class="bewerberboerse-modal-close">&times;</span>';
            html += '<div class="bewerberboerse-modal-header">';
            html += '<h2>Kontakt aufnehmen</h2>';
            html += '</div>';
            
            html += '<div class="bewerberboerse-modal-body">';
            html += '<div class="bewerberboerse-contact-content">';
            
            html += '<div class="bewerberboerse-contact-info">';
            html += '<h3>Sie können uns kontaktieren</h3>';
            html += '<p>Sie können uns per Telefon erreichen und die Resümee-ID angeben:</p>';
            html += '<div class="bewerberboerse-contact-hash-display">';
            html += '<strong>ID:</strong> ' + this.escapeHtml(hash.toUpperCase());
            html += '</div>';
            html += '</div>';
            
            html += '<div class="bewerberboerse-contact-form">';
            html += '<h3>Oder nutzen Sie das Kontaktformular</h3>';
            html += '<form id="bewerberboerse-contact-form">';
            html += '<input type="hidden" name="application_hash" value="' + this.escapeHtml(hash) + '">';
            html += '<div class="bewerberboerse-form-group">';
            html += '<label for="contact_name">Name *</label>';
            html += '<input type="text" id="contact_name" name="name" required>';
            html += '</div>';
            html += '<div class="bewerberboerse-form-group">';
            html += '<label for="contact_email">E-Mail *</label>';
            html += '<input type="email" id="contact_email" name="email" required>';
            html += '</div>';
            html += '<div class="bewerberboerse-form-group">';
            html += '<label for="contact_phone">Telefon</label>';
            html += '<input type="tel" id="contact_phone" name="phone">';
            html += '</div>';
            html += '<div class="bewerberboerse-form-group">';
            html += '<label for="contact_message">Nachricht</label>';
            html += '<textarea id="contact_message" name="message" rows="4"></textarea>';
            html += '</div>';
            html += '<div class="bewerberboerse-form-group bewerberboerse-checkbox-group">';
            html += '<label class="bewerberboerse-checkbox-label">';
            html += '<input type="checkbox" id="contact_datenschutz" name="datenschutz" required>';
            html += '<span class="bewerberboerse-checkbox-label-text">Ich habe die Datenschutzerklärung gelesen <span class="required">*</span></span>';
            html += '</label>';
            html += '</div>';
            html += '<button type="submit" class="bewerberboerse-submit-btn">Nachricht senden</button>';
            html += '</form>';
            html += '</div>';
            
            html += '</div>';
            html += '</div>';
            
            contactModal.html(html);
            contactModal.show();
            
            contactModal.find('.bewerberboerse-modal-close').on('click', function() {
                contactModal.hide();
            });
            
            contactModal.on('click', function(e) {
                if ($(e.target).hasClass('bewerberboerse-modal-overlay')) {
                    contactModal.hide();
                }
            });
            
            contactModal.find('#bewerberboerse-contact-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = {
                    application_hash: hash,
                    name: $('#contact_name').val(),
                    email: $('#contact_email').val(),
                    phone: $('#contact_phone').val() || null,
                    message: $('#contact_message').val() || null
                };
                
                $.ajax({
                    url: bewerberboerseData.apiUrl + '/contact-request',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': bewerberboerseData.nonce,
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify(formData),
                    dataType: 'json',
                    success: function(data) {
                        console.log('Contact request sent successfully:', data);
                        alert('Ihre Nachricht wurde erfolgreich gesendet!');
                        contactModal.hide();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error sending contact request:', error);
                        console.error('Response:', xhr.responseText);
                        alert('Fehler beim Senden der Nachricht. Bitte versuchen Sie es erneut.');
                    }
                });
            });
        }
    };
    
    $(document).ready(function() {
        BewerberboerseApp.init();
    });
    
})(jQuery);
