/**
 * SwiftLMS Quiz Player
 *
 * @package SwiftLMS
 */

(function($) {
    'use strict';

    const QuizPlayer = {
        quizData: null,
        attemptId: null,
        currentQuestion: 0,
        answers: {},
        startTime: null,
        questionStartTime: null,
        timerInterval: null,
        timeRemaining: 0,

        init: function() {
            this.loadQuiz();
        },

        loadQuiz: function() {
            const self = this;

            $.ajax({
                url: swiftlmsQuiz.restUrl + swiftlmsQuiz.quizId,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', swiftlmsQuiz.nonce);
                },
                success: function(data) {
                    self.quizData = data;
                    self.renderQuizIntro();
                },
                error: function() {
                    self.showError('Failed to load quiz.');
                }
            });
        },

        renderQuizIntro: function() {
            const quiz = this.quizData;
            const $container = $('#sfls-quiz-container');

            let attemptsHtml = '';
            if (quiz.attempts && quiz.attempts.length > 0) {
                attemptsHtml = '<div class="sfls-previous-attempts"><h4>Previous Attempts</h4><ul>';
                quiz.attempts.forEach(function(attempt, index) {
                    const statusClass = attempt.status === 'passed' ? 'passed' : 'failed';
                    attemptsHtml += `
                        <li class="${statusClass}">
                            Attempt ${attempt.attempt_number}: ${attempt.percentage}%
                            <span class="status">${attempt.status}</span>
                        </li>
                    `;
                });
                attemptsHtml += '</ul></div>';
            }

            let html = `
                <div class="sfls-quiz-intro">
                    <h2>${quiz.title}</h2>
                    <div class="sfls-quiz-description">${quiz.description}</div>

                    <div class="sfls-quiz-info">
                        <div class="sfls-info-item">
                            <span class="label">Questions:</span>
                            <span class="value">${quiz.question_count}</span>
                        </div>
                        <div class="sfls-info-item">
                            <span class="label">Total Points:</span>
                            <span class="value">${quiz.total_points}</span>
                        </div>
                        <div class="sfls-info-item">
                            <span class="label">Passing Score:</span>
                            <span class="value">${quiz.passing_score}%</span>
                        </div>
                        ${quiz.time_limit > 0 ? `
                        <div class="sfls-info-item">
                            <span class="label">Time Limit:</span>
                            <span class="value">${quiz.time_limit} minutes</span>
                        </div>
                        ` : ''}
                        ${quiz.attempts_allowed > 0 ? `
                        <div class="sfls-info-item">
                            <span class="label">Attempts Allowed:</span>
                            <span class="value">${quiz.attempts_allowed}</span>
                        </div>
                        ` : ''}
                    </div>

                    ${attemptsHtml}

                    ${quiz.can_start ? `
                    <button type="button" class="sfls-btn sfls-btn-primary" id="sfls-start-quiz">
                        ${quiz.current_attempt ? 'Resume Quiz' : 'Start Quiz'}
                    </button>
                    ` : `
                    <p class="sfls-notice sfls-notice-warning">You have reached the maximum number of attempts.</p>
                    `}
                </div>
            `;

            $container.html(html);

            $('#sfls-start-quiz').on('click', this.startQuiz.bind(this));
        },

        startQuiz: function() {
            const self = this;

            $.ajax({
                url: swiftlmsQuiz.restUrl + swiftlmsQuiz.quizId + '/start',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', swiftlmsQuiz.nonce);
                },
                success: function(data) {
                    self.attemptId = data.attempt_id;
                    self.startTime = new Date();

                    // Restore saved answers if resuming
                    if (data.resumed && data.answers) {
                        data.answers.forEach(function(answer) {
                            self.answers[answer.question_id] = JSON.parse(answer.user_answer);
                        });
                    }

                    self.renderQuiz();
                    self.startTimer();
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.error || 'Failed to start quiz.';
                    self.showError(msg);
                }
            });
        },

        renderQuiz: function() {
            const quiz = this.quizData;
            const $container = $('#sfls-quiz-container');

            let html = `
                <div class="sfls-quiz-player">
                    <div class="sfls-quiz-header">
                        <h3>${quiz.title}</h3>
                        ${quiz.time_limit > 0 ? '<div class="sfls-timer" id="sfls-timer"></div>' : ''}
                        ${quiz.show_progress ? '<div class="sfls-progress-bar"><div class="sfls-progress-fill" id="sfls-progress"></div></div>' : ''}
                    </div>

                    <div class="sfls-questions-container" id="sfls-questions">
                        ${this.renderQuestions()}
                    </div>

                    <div class="sfls-quiz-navigation">
                        ${quiz.display_mode === 'one' ? `
                        <button type="button" class="sfls-btn sfls-btn-secondary" id="sfls-prev-question" style="display:none;">
                            Previous
                        </button>
                        <button type="button" class="sfls-btn sfls-btn-secondary" id="sfls-next-question">
                            Next
                        </button>
                        ` : ''}
                        <button type="button" class="sfls-btn sfls-btn-primary" id="sfls-submit-quiz">
                            Submit Quiz
                        </button>
                    </div>
                </div>
            `;

            $container.html(html);

            this.bindQuizEvents();
            this.updateProgress();

            if (quiz.display_mode === 'one') {
                this.showQuestion(0);
            }
        },

        renderQuestions: function() {
            const self = this;
            const quiz = this.quizData;
            let html = '';

            quiz.questions.forEach(function(question, index) {
                const isHidden = quiz.display_mode === 'one' && index > 0;
                const savedAnswer = self.answers[question.id];

                html += `
                    <div class="sfls-question" data-id="${question.id}" data-index="${index}"
                         style="${isHidden ? 'display:none;' : ''}">
                        ${quiz.show_question_numbers ? `<span class="sfls-question-number">Question ${index + 1}</span>` : ''}
                        <div class="sfls-question-content">${question.content}</div>
                        <div class="sfls-question-answers">
                            ${self.renderAnswerInput(question, savedAnswer)}
                        </div>
                        ${quiz.show_hints && question.hint ? `
                        <div class="sfls-hint-container">
                            <button type="button" class="sfls-show-hint">Show Hint</button>
                            <div class="sfls-hint" style="display:none;">${question.hint}</div>
                        </div>
                        ` : ''}
                        <div class="sfls-feedback" style="display:none;"></div>
                    </div>
                `;
            });

            return html;
        },

        renderAnswerInput: function(question, savedAnswer) {
            let html = '';

            switch (question.type) {
                case 'multiple_choice':
                case 'true_false':
                    for (const [key, value] of Object.entries(question.answers)) {
                        const checked = savedAnswer == key ? 'checked' : '';
                        html += `
                            <label class="sfls-answer-option">
                                <input type="radio" name="question_${question.id}" value="${key}" ${checked}>
                                <span>${value}</span>
                            </label>
                        `;
                    }
                    break;

                case 'multiple_answer':
                    const selectedAnswers = Array.isArray(savedAnswer) ? savedAnswer : [];
                    for (const [key, value] of Object.entries(question.answers)) {
                        const checked = selectedAnswers.includes(key) ? 'checked' : '';
                        html += `
                            <label class="sfls-answer-option">
                                <input type="checkbox" name="question_${question.id}" value="${key}" ${checked}>
                                <span>${value}</span>
                            </label>
                        `;
                    }
                    break;

                case 'fill_blank':
                    // Count blanks in content
                    const blankCount = (question.content.match(/\[blank\]/g) || []).length;
                    const blankAnswers = Array.isArray(savedAnswer) ? savedAnswer : [];
                    for (let i = 0; i < blankCount; i++) {
                        html += `
                            <div class="sfls-blank-input">
                                <label>Blank ${i + 1}:</label>
                                <input type="text" name="question_${question.id}_blank_${i}"
                                       class="sfls-blank-field" data-blank="${i}"
                                       value="${blankAnswers[i] || ''}">
                            </div>
                        `;
                    }
                    break;

                case 'matching':
                    const matchAnswers = savedAnswer || {};
                    html += '<div class="sfls-matching-grid">';
                    question.left_items.forEach(function(item, i) {
                        html += `
                            <div class="sfls-matching-row">
                                <span class="sfls-match-left">${item}</span>
                                <select name="question_${question.id}_match_${i}" class="sfls-match-select" data-index="${i}">
                                    <option value="">-- Select --</option>
                                    ${question.right_items.map(r => `
                                        <option value="${r}" ${matchAnswers[i] === r ? 'selected' : ''}>${r}</option>
                                    `).join('')}
                                </select>
                            </div>
                        `;
                    });
                    html += '</div>';
                    break;

                case 'ordering':
                    html += '<ul class="sfls-ordering-list" id="ordering_' + question.id + '">';
                    const orderedItems = Array.isArray(savedAnswer) ? savedAnswer : question.items;
                    orderedItems.forEach(function(item) {
                        html += `
                            <li class="sfls-ordering-item" data-value="${item}">
                                <span class="sfls-order-handle">&#9776;</span>
                                ${item}
                            </li>
                        `;
                    });
                    html += '</ul>';
                    break;

                case 'short_answer':
                    html += `
                        <input type="text" name="question_${question.id}" class="sfls-short-answer"
                               value="${savedAnswer || ''}" placeholder="Enter your answer">
                    `;
                    break;

                case 'essay':
                    html += `
                        <textarea name="question_${question.id}" class="sfls-essay-answer"
                                  rows="8" placeholder="Write your answer...">${savedAnswer || ''}</textarea>
                        ${question.min_words > 0 ? `
                        <div class="sfls-word-count">
                            Minimum words: ${question.min_words} |
                            Current: <span class="count">0</span>
                        </div>
                        ` : ''}
                    `;
                    break;
            }

            return html;
        },

        bindQuizEvents: function() {
            const self = this;

            // Answer changes
            $(document).on('change', '.sfls-question input, .sfls-question select', function() {
                self.saveAnswer($(this).closest('.sfls-question'));
            });

            $(document).on('input', '.sfls-question textarea, .sfls-short-answer, .sfls-blank-field', function() {
                self.saveAnswer($(this).closest('.sfls-question'));
            });

            // Essay word count
            $(document).on('input', '.sfls-essay-answer', function() {
                const words = $(this).val().trim().split(/\s+/).filter(w => w).length;
                $(this).closest('.sfls-question').find('.sfls-word-count .count').text(words);
            });

            // Show hint
            $(document).on('click', '.sfls-show-hint', function() {
                $(this).hide().next('.sfls-hint').slideDown();
            });

            // Navigation
            $('#sfls-prev-question').on('click', function() {
                self.showQuestion(self.currentQuestion - 1);
            });

            $('#sfls-next-question').on('click', function() {
                self.showQuestion(self.currentQuestion + 1);
            });

            // Submit
            $('#sfls-submit-quiz').on('click', function() {
                self.submitQuiz();
            });

            // Ordering sortable
            $('.sfls-ordering-list').sortable({
                handle: '.sfls-order-handle',
                update: function() {
                    self.saveAnswer($(this).closest('.sfls-question'));
                }
            });
        },

        saveAnswer: function($question) {
            const self = this;
            const questionId = $question.data('id');
            const question = this.quizData.questions.find(q => q.id == questionId);
            let answer = null;

            switch (question.type) {
                case 'multiple_choice':
                case 'true_false':
                    answer = $question.find('input[type="radio"]:checked').val();
                    break;

                case 'multiple_answer':
                    answer = [];
                    $question.find('input[type="checkbox"]:checked').each(function() {
                        answer.push($(this).val());
                    });
                    break;

                case 'fill_blank':
                    answer = [];
                    $question.find('.sfls-blank-field').each(function() {
                        answer.push($(this).val());
                    });
                    break;

                case 'matching':
                    answer = {};
                    $question.find('.sfls-match-select').each(function() {
                        const idx = $(this).data('index');
                        answer[idx] = $(this).val();
                    });
                    break;

                case 'ordering':
                    answer = [];
                    $question.find('.sfls-ordering-item').each(function() {
                        answer.push($(this).data('value'));
                    });
                    break;

                case 'short_answer':
                    answer = $question.find('.sfls-short-answer').val();
                    break;

                case 'essay':
                    answer = $question.find('.sfls-essay-answer').val();
                    break;
            }

            this.answers[questionId] = answer;

            // Calculate time spent on this question
            const now = new Date();
            const timeSpent = this.questionStartTime ? Math.floor((now - this.questionStartTime) / 1000) : 0;
            this.questionStartTime = now;

            // Save to server
            $.ajax({
                url: swiftlmsQuiz.restUrl + 'attempts/' + this.attemptId + '/answer',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', swiftlmsQuiz.nonce);
                },
                data: {
                    question_id: questionId,
                    answer: answer,
                    time_spent: timeSpent
                },
                success: function(data) {
                    if (data.feedback && self.quizData.instant_feedback) {
                        self.showFeedback($question, data.feedback);
                    }
                }
            });

            this.updateProgress();
        },

        showFeedback: function($question, feedback) {
            const $feedback = $question.find('.sfls-feedback');
            const className = feedback.is_correct ? 'correct' : (feedback.partial > 0 ? 'partial' : 'incorrect');

            let html = `<div class="sfls-feedback-${className}">`;
            if (feedback.is_correct) {
                html += '<strong>' + swiftlmsQuiz.i18n.correct + '</strong>';
            } else if (feedback.partial > 0) {
                html += `<strong>Partially Correct (${Math.round(feedback.partial * 100)}%)</strong>`;
            } else {
                html += '<strong>' + swiftlmsQuiz.i18n.incorrect + '</strong>';
            }

            if (feedback.feedback) {
                html += '<p>' + feedback.feedback + '</p>';
            }
            html += '</div>';

            $feedback.html(html).slideDown();
        },

        showQuestion: function(index) {
            const total = this.quizData.questions.length;
            if (index < 0) index = 0;
            if (index >= total) index = total - 1;

            this.currentQuestion = index;
            this.questionStartTime = new Date();

            $('.sfls-question').hide();
            $(`.sfls-question[data-index="${index}"]`).show();

            // Update navigation buttons
            $('#sfls-prev-question').toggle(index > 0);
            $('#sfls-next-question').toggle(index < total - 1);
            $('#sfls-submit-quiz').toggle(index === total - 1);

            this.updateProgress();
        },

        updateProgress: function() {
            const total = this.quizData.questions.length;
            const answered = Object.keys(this.answers).filter(k => {
                const val = this.answers[k];
                if (Array.isArray(val)) return val.length > 0;
                if (typeof val === 'object') return Object.values(val).some(v => v);
                return val !== null && val !== undefined && val !== '';
            }).length;

            const percent = Math.round((answered / total) * 100);
            $('#sfls-progress').css('width', percent + '%');
        },

        startTimer: function() {
            const self = this;
            const timeLimit = this.quizData.time_limit;

            if (!timeLimit || timeLimit <= 0) return;

            this.timeRemaining = timeLimit * 60; // Convert to seconds
            this.updateTimerDisplay();

            this.timerInterval = setInterval(function() {
                self.timeRemaining--;
                self.updateTimerDisplay();

                if (self.timeRemaining <= 0) {
                    clearInterval(self.timerInterval);
                    alert(swiftlmsQuiz.i18n.timeUp);
                    self.submitQuiz();
                }
            }, 1000);
        },

        updateTimerDisplay: function() {
            const minutes = Math.floor(this.timeRemaining / 60);
            const seconds = this.timeRemaining % 60;
            const display = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            $('#sfls-timer').text(display);

            // Warning colors
            if (this.timeRemaining <= 60) {
                $('#sfls-timer').addClass('warning');
            } else if (this.timeRemaining <= 300) {
                $('#sfls-timer').addClass('caution');
            }
        },

        submitQuiz: function() {
            const self = this;

            // Check for unanswered questions
            const total = this.quizData.questions.length;
            const answered = Object.keys(this.answers).length;

            if (answered < total) {
                if (!confirm(swiftlmsQuiz.i18n.unanswered)) {
                    return;
                }
            }

            if (!confirm(swiftlmsQuiz.i18n.confirmSubmit)) {
                return;
            }

            clearInterval(this.timerInterval);

            const $btn = $('#sfls-submit-quiz');
            $btn.prop('disabled', true).text(swiftlmsQuiz.i18n.submitting);

            $.ajax({
                url: swiftlmsQuiz.restUrl + 'attempts/' + this.attemptId + '/submit',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', swiftlmsQuiz.nonce);
                },
                success: function(result) {
                    self.showResults(result);
                },
                error: function() {
                    alert(swiftlmsQuiz.i18n.errorSubmit);
                    $btn.prop('disabled', false).text('Submit Quiz');
                }
            });
        },

        showResults: function(result) {
            const self = this;
            const $container = $('#sfls-quiz-container');

            const statusClass = result.passed ? 'passed' : 'failed';
            const statusText = result.passed ? 'Passed!' : 'Failed';

            let html = `
                <div class="sfls-quiz-results ${statusClass}">
                    <div class="sfls-result-header">
                        <h2>Quiz Complete</h2>
                        <div class="sfls-result-status">${statusText}</div>
                    </div>

                    <div class="sfls-result-score">
                        <div class="sfls-score-circle">
                            <span class="percentage">${result.percentage}%</span>
                        </div>
                        <p>You scored ${result.earned_points} out of ${result.total_points} points</p>
                        <p>Passing score: ${result.passing_score}%</p>
                    </div>

                    <div class="sfls-result-stats">
                        <div class="stat">
                            <span class="label">Time Spent</span>
                            <span class="value">${this.formatTime(result.time_spent)}</span>
                        </div>
                    </div>

                    ${result.pending_review ? `
                    <div class="sfls-notice sfls-notice-info">
                        Some questions require manual grading. Your final score may change.
                    </div>
                    ` : ''}

                    ${this.quizData.show_review ? `
                    <button type="button" class="sfls-btn sfls-btn-secondary" id="sfls-review-answers">
                        Review Answers
                    </button>
                    ` : ''}

                    <button type="button" class="sfls-btn sfls-btn-primary" id="sfls-return-course">
                        Return to Course
                    </button>
                </div>
            `;

            $container.html(html);

            $('#sfls-review-answers').on('click', function() {
                self.showReview(result);
            });

            $('#sfls-return-course').on('click', function() {
                const courseId = self.quizData.course_id;
                if (courseId) {
                    window.location.href = '/?p=' + courseId;
                } else {
                    window.location.reload();
                }
            });
        },

        showReview: function(result) {
            const self = this;
            const $container = $('#sfls-quiz-container');

            let html = '<div class="sfls-quiz-review"><h3>Answer Review</h3>';

            this.quizData.questions.forEach(function(question, index) {
                const detail = result.details[question.id] || {};
                const userAnswer = self.answers[question.id];
                const statusClass = detail.is_correct ? 'correct' : (detail.partial > 0 ? 'partial' : 'incorrect');

                html += `
                    <div class="sfls-review-question ${statusClass}">
                        <div class="sfls-review-header">
                            <span class="question-num">Question ${index + 1}</span>
                            <span class="points">${detail.points_earned || 0}/${question.points} pts</span>
                        </div>
                        <div class="sfls-question-content">${question.content}</div>
                        <div class="sfls-your-answer">
                            <strong>Your Answer:</strong> ${self.formatAnswer(question, userAnswer)}
                        </div>
                        ${detail.feedback ? `<div class="sfls-explanation">${detail.feedback}</div>` : ''}
                    </div>
                `;
            });

            html += '<button type="button" class="sfls-btn sfls-btn-secondary" id="sfls-back-results">Back to Results</button></div>';

            $container.html(html);

            $('#sfls-back-results').on('click', function() {
                self.showResults(result);
            });
        },

        formatAnswer: function(question, answer) {
            if (!answer) return '<em>No answer</em>';

            switch (question.type) {
                case 'multiple_choice':
                case 'true_false':
                    return question.answers[answer] || answer;

                case 'multiple_answer':
                    if (!Array.isArray(answer)) return answer;
                    return answer.map(a => question.answers[a] || a).join(', ');

                case 'fill_blank':
                    if (!Array.isArray(answer)) return answer;
                    return answer.join(', ');

                case 'matching':
                    if (typeof answer !== 'object') return answer;
                    let matches = [];
                    for (const [idx, val] of Object.entries(answer)) {
                        if (val) {
                            matches.push(`${question.left_items[idx]} → ${val}`);
                        }
                    }
                    return matches.join('<br>');

                case 'ordering':
                    if (!Array.isArray(answer)) return answer;
                    return answer.join(' → ');

                default:
                    return String(answer);
            }
        },

        formatTime: function(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}m ${secs}s`;
        },

        showError: function(message) {
            $('#sfls-quiz-container').html(`
                <div class="sfls-notice sfls-notice-error">
                    <p>${message}</p>
                </div>
            `);
        }
    };

    $(document).ready(function() {
        if ($('#sfls-quiz-container').length) {
            QuizPlayer.init();
        }
    });

})(jQuery);
