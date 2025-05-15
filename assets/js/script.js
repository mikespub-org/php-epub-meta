// Main application code
const bookapi = {
    modal: null,
    
    resulttpl: `
        <div class="card result-card mb-3">
            <div class="row g-0">
                <div class="col-md-3">
                    <img src="" class="img-fluid rounded-start" alt="">
                </div>
                <div class="col-md-9">
                    <div class="card-body">
                        <div class="float-end">
                            <button class="btn btn-sm btn-primary btn-repl">Replace</button>
                            <button class="btn btn-sm btn-secondary btn-fill">Fill In</button>
                            <button class="btn btn-sm btn-info btn-cmp">Compare</button>
                        </div>
                        <h5 class="card-title title"></h5>
                        <p class="card-text authors"></p>
                        <p class="card-text description"></p>
                        <p class="card-text">
                            <small class="text-muted">
                                <span class="lang"></span>
                                <span class="publisher"></span>
                                <span class="subjects"></span>
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    `,
    
    init() {
        this.modal = new bootstrap.Modal(document.getElementById('bookapi'));
        
        // Event listeners
        document.getElementById('lookup-data').addEventListener('click', () => this.open());
        document.querySelector('.btn-search').addEventListener('click', () => this.search());
        
        document.getElementById('bookapi-q').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.search();
            }
        });
        
        document.getElementById('bookapi-l').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.search();
            }
        });
        
        document.getElementById('close-comparison').addEventListener('click', () => {
            document.getElementById('comparison-container').classList.add('d-none');
            document.getElementById('bookpanel').classList.remove('d-none');
        });
        
        // Add Escape key handler
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const container = document.getElementById('comparison-container');
                if (!container.classList.contains('d-none')) {
                    container.classList.add('d-none');
                    document.getElementById('bookpanel').classList.remove('d-none');
                }
            }
        });

        document.getElementById('accept-all').addEventListener('click', () => this.acceptAll());
        document.getElementById('revert-all').addEventListener('click', () => this.revertAll());
    },
    
    open() {
        const query = document.querySelector('#bookpanel input[name=title]').value;
        document.getElementById('bookapi-q').value = query;
        const language = document.querySelector('#bookpanel input[name=language]').value;
        document.getElementById('bookapi-l').value = language;
        
        this.modal.show();
        this.search();
    },
    
    async search() {
        const output = document.getElementById('bookapi-out');
        output.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
        
        try {
            const params = new URLSearchParams({
                api: document.getElementById('bookapi-q').value,
                lang: document.getElementById('bookapi-l').value
            });
            const response = await fetch('?' + params.toString());
            
            const data = await response.json();
            this.searchdone(data);
        } catch (error) {
            output.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
        }
    },
    
    searchdone(data) {
        const output = document.getElementById('bookapi-out');
        
        if (data.totalItems === 0) {
            if (data.error) {
                output.innerHTML = `<div class="alert alert-danger">Error: ${data.error}<br>Please try again later...</div>`;
                return;
            }
            output.innerHTML = '<div class="alert alert-warning">No results found.<br>Try adjusting the query and retry.</div>';
            return;
        }
        
        output.innerHTML = '';
        data.items.forEach(item => {
            const resultEl = document.createElement('div');
            resultEl.innerHTML = this.resulttpl;
            const result = resultEl.firstElementChild;
            
            if (item.volumeInfo.title) {
                result.querySelector('.title').textContent = item.volumeInfo.title;
            }
            if (item.volumeInfo.authors) {
                result.querySelector('.authors').textContent = item.volumeInfo.authors.join(', ');
            }
            if (item.volumeInfo.description) {
                result.querySelector('.description').textContent = item.volumeInfo.description;
            }
            if (item.volumeInfo.language) {
                result.querySelector('.lang').textContent = `[${item.volumeInfo.language}]`;
            }
            if (item.volumeInfo.publisher) {
                result.querySelector('.publisher').textContent = item.volumeInfo.publisher;
            }
            if (item.volumeInfo.categories) {
                result.querySelector('.subjects').textContent = item.volumeInfo.categories.join(', ');
            }
            if (item.volumeInfo.imageLinks?.thumbnail) {
                result.querySelector('img').src = item.volumeInfo.imageLinks.thumbnail;
            }
            
            result.querySelector('.btn-repl').addEventListener('click', () => this.replace(item.volumeInfo));
            result.querySelector('.btn-fill').addEventListener('click', () => this.fillin(item.volumeInfo));
            result.querySelector('.btn-cmp').addEventListener('click', () => this.compare(item.volumeInfo));
            
            output.appendChild(result);
        });
    },
    
    replace(item) {
        this.updateFields(item, false);
        this.modal.hide();
    },
    
    fillin(item) {
        this.updateFields(item, true);
        this.modal.hide();
    },
    
    // Add these new methods:
    acceptAll() {
        // Show confirmation dialog
        if (!confirm('Accept all changes? This will update all fields with the new values.')) {
            return;
        }
        
        // Process all fields with changes
        const fields = document.querySelectorAll('.comparison-field');
        fields.forEach(field => {
            const acceptBtn = field.querySelector('.btn-accept');
            if (acceptBtn) {
                acceptBtn.click();
            }
        });
        
        // Close comparison view since all changes are processed
        this.closeComparison();
    },

    revertAll() {
        // Show confirmation dialog
        if (!confirm('Revert all changes? This will keep all current values.')) {
            return;
        }
        
        // Process all fields with changes
        const fields = document.querySelectorAll('.comparison-field');
        fields.forEach(field => {
            const revertBtn = field.querySelector('.btn-revert');
            if (revertBtn) {
                revertBtn.click();
            }
        });
        
        // Close comparison view since all changes are processed
        this.closeComparison();
    },

    closeComparison() {
        document.getElementById('comparison-container').classList.add('d-none');
        document.getElementById('bookpanel').classList.remove('d-none');
    },

    compare(item) {
        const fields = [
            { id: 'title', label: 'Title' },
            { id: 'publisher', label: 'Publisher' },
            { id: 'subjects', label: 'Subjects', getValue: info => info.categories?.join(', ') || '' },
            { id: 'language', label: 'Language' },
            { id: 'description', label: 'Description', isHtml: true }
        ];
        
        const container = document.querySelector('.comparison-content');
        container.innerHTML = '';
        
        // Compare each field
        fields.forEach(field => {
            const currentValue = this.getCurrentValue(field.id);
            const newValue = field.getValue ? field.getValue(item) : (item[field.id] || '');
            
            if (currentValue !== newValue) {
                container.appendChild(this.createComparisonField(field.label, currentValue, newValue, field.isHtml));
            }
        });
        
        // Compare authors separately
        const currentAuthors = this.getCurrentAuthors();
        if (item.authors && JSON.stringify(currentAuthors) !== JSON.stringify(item.authors)) {
            container.appendChild(this.createAuthorsComparison(currentAuthors, item.authors));
        }
        
        // After adding all fields to container
        const hasChanges = container.querySelectorAll('.comparison-field').length > 0;
        
        if (!hasChanges) {
            alert('No differences found between current and new values.');
            return;
        }

        // Show comparison view
        document.getElementById('comparison-container').classList.remove('d-none');
        document.getElementById('bookpanel').classList.add('d-none');
        
        this.modal.hide();
    },

    // Update the createComparisonField method in bookapi:
    createComparisonField(label, currentValue, newValue, isHtml = false) {
        const field = document.createElement('div');
        field.className = `comparison-field ${label.toLowerCase()}`;
        const fieldId = label.toLowerCase().replace(/\s+/g, '-');
        
        field.innerHTML = `
            <div class="field-header d-flex justify-content-between align-items-center">
                <span>${label}</span>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-success btn-accept" data-field="${fieldId}">
                        <i class="bi bi-check-lg"></i> Accept
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-revert" data-field="${fieldId}">
                        <i class="bi bi-arrow-counterclockwise"></i> Revert
                    </button>
                </div>
            </div>
            <div class="field-values">
                <div class="value-container">
                    <div class="value-label">Current</div>
                    <div class="value-content" data-field="${fieldId}-current">
                        ${isHtml ? currentValue : this.escapeHtml(currentValue) || '<span class="text-muted">Empty</span>'}
                    </div>
                </div>
                <div class="value-container changed">
                    <div class="value-label">New</div>
                    <div class="value-content" data-field="${fieldId}-new">
                        ${isHtml ? newValue : this.escapeHtml(newValue) || '<span class="text-muted">Empty</span>'}
                    </div>
                </div>
            </div>
        `;
        
        // Add event listeners for accept/revert buttons
        field.querySelector('.btn-accept').addEventListener('click', () => {
            this.acceptChange(fieldId, label.toLowerCase(), newValue, isHtml);
        });
        
        field.querySelector('.btn-revert').addEventListener('click', () => {
            this.revertChange(fieldId, label.toLowerCase(), currentValue, isHtml);
        });
        
        return field;
    },

    // Similar update for createAuthorsComparison
    createAuthorsComparison(currentAuthors, newAuthors) {
        const field = document.createElement('div');
        field.className = 'comparison-field authors';
        
        let rows = '';
        const maxLength = Math.max(currentAuthors.length, newAuthors.length);
        
        for (let i = 0; i < maxLength; i++) {
            const current = currentAuthors[i] || '';
            const next = newAuthors[i] || '';
            const changed = current !== next;
            
            rows += `
                <tr>
                    <td>${this.escapeHtml(current) || '<span class="text-muted">Empty</span>'}</td>
                    <td class="${changed ? 'changed' : ''}">${this.escapeHtml(next) || '<span class="text-muted">Empty</span>'}</td>
                </tr>
            `;
        }
        
        field.innerHTML = `
            <div class="field-header d-flex justify-content-between align-items-center">
                <span>Authors</span>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-success btn-accept" data-field="authors">
                        <i class="bi bi-check-lg"></i> Accept
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-revert" data-field="authors">
                        <i class="bi bi-arrow-counterclockwise"></i> Revert
                    </button>
                </div>
            </div>
            <div class="field-values">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Current</th>
                            <th>New</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                </table>
            </div>
        `;
        
        // Add event listeners for accept/revert buttons
        field.querySelector('.btn-accept').addEventListener('click', () => {
            this.acceptAuthors(newAuthors);
        });
        
        field.querySelector('.btn-revert').addEventListener('click', () => {
            this.acceptAuthors(currentAuthors);
        });
        
        return field;
    },

    // Add these new methods to handle accept/revert actions:
    acceptChange(fieldId, name, value, isHtml = false) {
        if (isHtml && name === 'description') {
            quillEditor.root.innerHTML = value;
            document.querySelector('#description-input').value = value;
        } else {
            const input = document.querySelector(`#bookpanel [name="${name}"]`);
            if (input) {
                input.value = value;
            }
        }
        
        // Update the comparison view
        const field = document.querySelector(`.comparison-field.${name}`);
        if (field) {
            // Update current value display
            const currentContent = field.querySelector(`[data-field="${fieldId}-current"]`);
            const newContent = field.querySelector(`[data-field="${fieldId}-new"]`);
            if (currentContent && newContent) {
                currentContent.innerHTML = newContent.innerHTML;
            }
            // Remove changed styling since values are now the same
            field.querySelector('.value-container.changed').classList.remove('changed');
            // Remove the field if we want to hide accepted changes
            // field.remove();
        }
    },

    revertChange(fieldId, name, value, isHtml = false) {
        // Same as acceptChange but keeps the original value
        if (isHtml && name === 'description') {
            quillEditor.root.innerHTML = value;
            document.querySelector('#description-input').value = value;
        } else {
            const input = document.querySelector(`#bookpanel [name="${name}"]`);
            if (input) {
                input.value = value;
            }
        }
        
        // Remove the field since we reverted the change
        const field = document.querySelector(`.comparison-field.${name}`);
        if (field) {
            field.remove();
        }
    },

    acceptAuthors(authors) {
        // Clear existing authors
        const container = document.getElementById('authors');
        container.innerHTML = '';
        
        // Add new authors
        authors.forEach((authorName, index) => {
            const row = author.createAuthorRow(index);
            row.querySelector('[name^="authorname"]').value = authorName;
            container.appendChild(row);
        });
        
        // Remove the comparison field
        const field = document.querySelector('.comparison-field.authors');
        if (field) {
            field.remove();
        }
    },

    getCurrentValue(fieldId) {
        if (fieldId === 'description') {
            return quillEditor ? quillEditor.root.innerHTML : '';
        }
        return document.querySelector(`#bookpanel [name="${fieldId}"]`)?.value || '';
    },

    getCurrentAuthors() {
        const authors = [];
        document.querySelectorAll('.author-row').forEach(row => {
            const name = row.querySelector('input[name^="authorname"]').value;
            if (name) {
                authors.push(name);
            }
        });
        return authors;
    },

    escapeHtml(text) {
        if (!text) return text;
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    updateFields(item, checkEmpty = false, suffix = '') {
        const setValue = (name, value) => {
            const el = document.querySelector(`#bookpanel [name="${name}${suffix}"]`);
            if (el && (!checkEmpty || !el.value)) {
                el.value = value;
                if (name === 'description') {
                    if (quillEditor) {
                        quillEditor.root.innerHTML = value;
                    }
                }
            }
        };
        
        if (item.title) setValue('title', item.title);
        if (item.description) setValue('description', item.description);
        if (item.language) setValue('language', item.language);
        if (item.publisher) setValue('publisher', item.publisher);
        if (item.categories) setValue('subjects', item.categories.join(', '));
        if (item.imageLinks?.thumbnail) {
            setValue('coverurl', item.imageLinks.thumbnail);
            document.getElementById('cover').src = item.imageLinks.thumbnail;
            document.getElementById('cover').classList.remove('noimg');
        }
    }
};

// Author management
const author = {
    init() {
        document.getElementById('add-author').addEventListener('click', () => this.add());
        
        // Add remove buttons to existing author rows
        document.querySelectorAll('.author-row').forEach(row => {
            this.addRemoveButton(row);
        });
    },
    
    createAuthorRow(index) {
        const row = document.createElement('div');
        row.className = 'author-row mb-2';
        row.innerHTML = `
            <div class="row g-2">
                <div class="col">
                    <input type="text" class="form-control" name="authorname[${index}]" placeholder="Author Name">
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="authoras[${index}]" placeholder="Sort As">
                </div>
            </div>
        `;
        this.addRemoveButton(row);
        return row;
    },
    
    addRemoveButton(row) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-link text-danger remove-author';
        btn.innerHTML = '&times;';
        btn.addEventListener('click', () => row.remove());
        row.appendChild(btn);
    },
    
    add() {
        const container = document.getElementById('authors');
        const index = container.querySelectorAll('.author-row').length;
        container.appendChild(this.createAuthorRow(index));
    }
};

// Rich text editor initialization
let quillEditor = null;

function initializeEditor() {
    quillEditor = new Quill('#description', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'header': [1, 2, 3, 4, 5, false] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['link'],
                ['clean']
            ]
        },
        formats: [
            'bold', 'italic', 'underline', 'strike',
            'header', 'list', 'align', 'link'
        ]
    });
    // Keep hidden input in sync with editor content
    quillEditor.on('text-change', function() {
        document.querySelector('#description-input').value = quillEditor.root.innerHTML;
    });
    // Set initial value to hidden input
    document.querySelector('#description-input').value = quillEditor.root.innerHTML;
}

// Initialize everything when the DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('bookapi')) {
        bookapi.init();
    }
    if (document.getElementById('add-author')) {
        author.init();
    }
    if (document.getElementById('description')) {
        initializeEditor();
    }

    // Scroll to currently selected book
    //const current = document.querySelector('#booklist .active');
    //if (current) {
    //    current.scrollIntoView({ behavior: 'smooth' });
    //}
});
