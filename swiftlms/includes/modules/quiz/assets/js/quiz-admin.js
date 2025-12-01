/**
 * SwiftLMS Quiz Admin
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    const QuizAdmin = {
        init: function() {
            this.bindEvents();
            this.initSortable();
        },

        bindEvents: function() {
            // Question type change
            $('#sfls_question_type').on('change', this.handleQuestionTypeChange);

            // Add answer option
            $(document).on('click', '#sfls-add-answer', this.addAnswerOption);
            $(document).on('click', '.sfls-remove-answer', this.removeAnswerOption);

            // Add blank
            $(document).on('click', '#sfls-add-blank', this.addBlank);

            // Add matching pair
            $(document).on('click', '#sfls-add-match', this.addMatchingPair);
            $(document).on('click', '.sfls-remove-match', this.removeMatchingPair);

            // Add ordering item
            $(document).on('click', '#sfls-add-order-item', this.addOrderingItem);
            $(document).on('click', '.sfls-remove-order', this.removeOrderingItem);

            // Quiz question picker
            $('#sfls-add-existing-question').on('click', this.openQuestionPicker);
            $(document).on('click', '.sfls-modal-close, #sfls-cancel-questions', this.closeQuestionPicker);
            $('#sfls-insert-questions').on('click', this.insertSelectedQuestions);
            $(document).on('click', '.sfls-remove-question', this.removeQuestion);

            // Question search
            $('#sfls-question-search').on('input', this.debounce(this.searchQuestions, 300));
            $('#sfls-question-type-filter, #sfls-question-cat-filter').on('change', this.searchQuestions);

            // Course/Lesson relationship
            $('#sfls_course_id').on('change', this.loadLessons);
        },

        initSortable: function() {
            // Answers list sortable
            $('#sfls-answers-list, #sfls-ordering-list').sortable({
                handle: '.sfls-answer-handle',
                placeholder: 'sfls-sortable-placeholder',
                update: function() {
                    QuizAdmin.updateOrderNumbers();
                }
            });

            // Quiz questions sortable
            $('#sfls-questions-list').sortable({
                handle: '.sfls-drag-handle',
                placeholder: 'sfls-sortable-placeholder',
                update: function() {
                    QuizAdmin.updateQuestionTotals();
                }
            });
        },

        handleQuestionTypeChange: function() {
            const type = $(this).val();
            const $container = $('#sfls-answers-container');

            // Hide all answer types
            $container.find('.sfls-answer-type').hide();

            // Show relevant type
            switch (type) {
                case 'multiple_choice':
                case 'multiple_answer':
                    $container.find('.sfls-choice-answers').show();
                    // Update radio/checkbox
                    const inputType = type === 'multiple_answer' ? 'checkbox' : 'radio';
                    $container.find('.sfls-choice-answers input[name="sfls_correct_answers[]"]')
                        .attr('type', inputType);
                    break;
                case 'true_false':
                    $container.find('.sfls-choice-answers').show();
                    break;
                case 'fill_blank':
                    $container.find('.sfls-fill-blank-answers').show();
                    break;
                case 'matching':
                    $container.find('.sfls-matching-answers').show();
                    break;
                case 'ordering':
                    $container.find('.sfls-ordering-answers').show();
                    break;
                case 'short_answer':
                    $container.find('.sfls-short-answer').show();
                    break;
                case 'essay':
                    $container.find('.sfls-essay-answer').show();
                    break;
            }

            $container.attr('data-type', type);
        },

        addAnswerOption: function() {
            const $list = $('#sfls-answers-list');
            const index = $list.find('.sfls-answer-row').length;
            const type = $('#sfls_question_type').val();
            const inputType = type === 'multiple_answer' ? 'checkbox' : 'radio';

            const $row = $(`
                <div class="sfls-answer-row" data-index="${index}">
                    <span class="sfls-answer-handle dashicons dashicons-menu"></span>
                    <input type="${inputType}" name="sfls_correct_answers[]" value="${index}">
                    <input type="text" name="sfls_answers[]" value="" class="regular-text"
                           placeholder="${swiftlmsQuizAdmin.i18n.searchPlaceholder || 'Answer option'}">
                    <button type="button" class="button sfls-remove-answer">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `);

            $list.append($row);
            $row.find('input[type="text"]').focus();
        },

        removeAnswerOption: function() {
            $(this).closest('.sfls-answer-row').remove();
            QuizAdmin.reindexAnswers();
        },

        reindexAnswers: function() {
            $('#sfls-answers-list .sfls-answer-row').each(function(index) {
                $(this).attr('data-index', index);
                $(this).find('input[name="sfls_correct_answers[]"]').val(index);
            });
        },

        addBlank: function() {
            const $list = $('#sfls-blanks-list');
            const index = $list.find('.sfls-blank-row').length + 1;

            const $row = $(`
                <div class="sfls-blank-row">
                    <label>Blank ${index}:</label>
                    <input type="text" name="sfls_blank_answers[]" value="" class="regular-text"
                           placeholder="Accepted answer(s), comma-separated">
                </div>
            `);

            $list.append($row);
        },

        addMatchingPair: function() {
            const $list = $('#sfls-matching-list');
            const index = $list.find('.sfls-matching-row').length;

            const $row = $(`
                <div class="sfls-matching-row" data-index="${index}">
                    <input type="text" name="sfls_matching_left[]" value="" class="regular-text"
                           placeholder="Left item">
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                    <input type="text" name="sfls_matching_right[]" value="" class="regular-text"
                           placeholder="Right match">
                    <button type="button" class="button sfls-remove-match">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `);

            $list.append($row);
        },

        removeMatchingPair: function() {
            $(this).closest('.sfls-matching-row').remove();
        },

        addOrderingItem: function() {
            const $list = $('#sfls-ordering-list');
            const index = $list.find('.sfls-ordering-row').length;

            const $row = $(`
                <div class="sfls-ordering-row" data-index="${index}">
                    <span class="sfls-answer-handle dashicons dashicons-menu"></span>
                    <span class="sfls-order-number">${index + 1}</span>
                    <input type="text" name="sfls_ordering_items[]" value="" class="regular-text"
                           placeholder="Item">
                    <button type="button" class="button sfls-remove-order">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `);

            $list.append($row);
        },

        removeOrderingItem: function() {
            $(this).closest('.sfls-ordering-row').remove();
            QuizAdmin.updateOrderNumbers();
        },

        updateOrderNumbers: function() {
            $('#sfls-ordering-list .sfls-ordering-row').each(function(index) {
                $(this).find('.sfls-order-number').text(index + 1);
            });
        },

        openQuestionPicker: function() {
            $('#sfls-question-picker-modal').show();
            QuizAdmin.searchQuestions();
        },

        closeQuestionPicker: function() {
            $('#sfls-question-picker-modal').hide();
        },

        searchQuestions: function() {
            const search = $('#sfls-question-search').val();
            const type = $('#sfls-question-type-filter').val();
            const category = $('#sfls-question-cat-filter').val();

            // Get currently added question IDs
            const exclude = [];
            $('#sfls-questions-list .sfls-question-item').each(function() {
                exclude.push($(this).data('id'));
            });

            const $container = $('#sfls-available-questions');
            $container.html('<p class="loading">' + swiftlmsQuizAdmin.i18n.loading + '</p>');

            $.ajax({
                url: swiftlmsQuizAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sfls_search_questions',
                    nonce: swiftlmsQuizAdmin.nonce,
                    search: search,
                    type: type,
                    category: category,
                    exclude: exclude
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let html = '<div class="sfls-question-list">';
                        response.data.forEach(function(q) {
                            html += `
                                <label class="sfls-question-option">
                                    <input type="checkbox" value="${q.id}" data-title="${q.title}"
                                           data-type="${q.type}" data-points="${q.points}">
                                    <span class="sfls-question-info">
                                        <strong>${q.title}</strong>
                                        <span class="sfls-question-meta">
                                            <span class="type">${q.type}</span>
                                            <span class="points">${q.points} pts</span>
                                        </span>
                                    </span>
                                </label>
                            `;
                        });
                        html += '</div>';
                        $container.html(html);
                    } else {
                        $container.html('<p>' + swiftlmsQuizAdmin.i18n.noQuestions + '</p>');
                    }
                }
            });
        },

        insertSelectedQuestions: function() {
            const $list = $('#sfls-questions-list');

            $('#sfls-available-questions input:checked').each(function() {
                const $input = $(this);
                const id = $input.val();
                const title = $input.data('title');
                const type = $input.data('type');
                const points = $input.data('points');

                const $item = $(`
                    <div class="sfls-question-item" data-id="${id}">
                        <span class="sfls-drag-handle dashicons dashicons-menu"></span>
                        <input type="hidden" name="sfls_quiz_questions[]" value="${id}">
                        <span class="sfls-question-title">${title}</span>
                        <span class="sfls-question-meta">
                            <span class="sfls-question-type">${type}</span>
                            <span class="sfls-question-points">${points} pts</span>
                        </span>
                        <span class="sfls-question-actions">
                            <a href="post.php?post=${id}&action=edit" target="_blank" class="sfls-edit-question">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                            <button type="button" class="sfls-remove-question">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </span>
                    </div>
                `);

                $list.append($item);
            });

            QuizAdmin.updateQuestionTotals();
            QuizAdmin.closeQuestionPicker();
        },

        removeQuestion: function() {
            $(this).closest('.sfls-question-item').remove();
            QuizAdmin.updateQuestionTotals();
        },

        updateQuestionTotals: function() {
            let totalQuestions = 0;
            let totalPoints = 0;

            $('#sfls-questions-list .sfls-question-item').each(function() {
                totalQuestions++;
                const pointsText = $(this).find('.sfls-question-points').text();
                const points = parseFloat(pointsText) || 0;
                totalPoints += points;
            });

            $('#sfls-total-questions').text(totalQuestions);
            $('#sfls-total-points').text(totalPoints);
        },

        loadLessons: function() {
            const courseId = $(this).val();
            const $lessonSelect = $('#sfls_lesson_id');

            $lessonSelect.html('<option value="">— Select Lesson —</option>');

            if (!courseId) {
                return;
            }

            $.ajax({
                url: swiftlmsQuizAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sfls_get_lessons_for_course',
                    nonce: swiftlmsQuizAdmin.nonce,
                    course_id: courseId
                },
                success: function(response) {
                    if (response.success) {
                        response.data.forEach(function(lesson) {
                            $lessonSelect.append(`<option value="${lesson.id}">${lesson.title}</option>`);
                        });
                    }
                }
            });
        },

        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
    };

    $(document).ready(function() {
        QuizAdmin.init();
    });

})(jQuery);
