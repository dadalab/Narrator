<?php
/**
 * Plugin Name: Narrator - Text-to-Speech Widget
 * Description: Adds a floating text-to-speech widget that can read page content aloud with progress tracking
 * Version: 1.0.1
 * Author: // dadalab
 * Text Domain: narrator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class NarratorPlugin {
    
    public function __construct() {
        add_action('wp_footer', array($this, 'add_narrator_widget'));
        add_action('wp_ajax_extract_page_content', array($this, 'extract_page_content'));
        add_action('wp_ajax_nopriv_extract_page_content', array($this, 'extract_page_content'));
    }
    // Removed enqueue_scripts(): it requested narrator-widget.js/.css, files
    // that never existed alongside this plugin file. All CSS/JS ship inline
    // via add_narrator_widget() -> wp_footer. The enqueue calls only produced
    // two 404 requests on every page load. Fixed 2026-09-16.
    
    public function add_narrator_widget() {
        ?>
        <style>
        /* Visible focus style for accessibility */
        .narrator-focus:focus {
            outline: 2px solid var(--dl-blue) !important;
            outline-offset: 2px;
            box-shadow: 0 0 0 2px rgba(49, 120, 192, 0.2);
        }
        </style>
        <div id="narrator-widget" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; font-family: system-ui, sans-serif;">
            <!-- Collapsed State -->
            <div id="narrator-collapsed" class="narrator-state">
                <div class="narrator-headphones narrator-focus"
                     role="button"
                     aria-label="Open Narrator controls"
                     tabindex="0"
                     style="width: 56px; height: 56px; 
                        background: var(--dl-rule); border-radius: 25%; display: flex; align-items: center; 
                        justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm14.024-.983a1.125 1.125 0 0 1 0 1.966l-5.603 3.113A1.125 1.125 0 0 1 9 15.113V8.887c0-.857.921-1.4 1.671-.983l5.603 3.113Z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>
            
            <!-- Hover State -->
            <div id="narrator-hover" class="narrator-state" style="display: none;">
                <div style="background: var(--dl-aurora-bg); border-radius: 8px; padding: 16px; border: 1px solid rgba(15, 90, 65, 0.16); width: 200px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <div style="font-weight: 500; font-size: 14px; color: var(--dl-teal-ink);">Narration</div>
                            <div style="font-size: 12px; color: var(--dl-dusty-emerald);">Listen to this page</div>
                        </div>
                    </div>
                    <button id="narrator-play-btn" class="narrator-focus" style="width: 100%; background: var(--dl-dusty-emerald); color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;"
                        aria-label="Expand narration controls and start reading">
                        ▶ Start reading
                    </button>
                </div>
            </div>
            
            <!-- Expanded State -->
            <div id="narrator-expanded" class="narrator-state" style="display: none;">
                <div style="background: var(--dl-aurora-bg); border-radius: 8px; padding: 16px; border: 1px solid rgba(15, 90, 65, 0.16); width: 300px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div>
                                <div style="font-weight: 500; font-size: 14px; color: var(--dl-teal-ink);">Narration</div>
                                <div style="font-size: 12px; color: var(--dl-dusty-emerald);">Ready to read</div>
                            </div>
                        </div>
                        <button id="narrator-close-btn" class="narrator-focus" style="background: none; border: none; color: var(--dl-gray-1); cursor: pointer; font-size: 18px;"
                            aria-label="Close Narrator controls">×</button>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <button id="narrator-play-pause" class="narrator-focus" style="background: var(--dl-dusty-emerald); color: #fff; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;"
                                aria-label="Play page narration">
                                ▶ Play
                            </button>
                            <button id="narrator-stop" class="narrator-focus" style="background: transparent; color: var(--dl-teal-ink); box-shadow: inset 0 0 0 1px rgba(15, 90, 65, 0.2); border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;"
                                aria-label="Stop page narration">
                                ⏹ Stop
                            </button>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <span id="narrator-current-time" style="font-size: 12px; color: var(--dl-gray-1); min-width: 40px;" aria-live="polite">0:00</span>
                            <div style="flex: 1; height: 4px; background: var(--dl-rule); border-radius: 2px; position: relative;">
                                <div id="narrator-progress" style="height: 100%; background: var(--dl-blue); border-radius: 2px; width: 0%; transition: width 0.1s;"></div>
                            </div>
                            <span id="narrator-total-time" style="font-size: 12px; color: var(--dl-gray-1); min-width: 40px;">0:00</span>
                        </div>
                        
                        <div style="margin-top: 12px;">
                            <label for="narrator-speed" style="font-size: 12px; color: var(--dl-gray-1); display: block; margin-bottom: 4px;">Speed:</label>
                            <input type="range" id="narrator-speed" min="0.5" max="2" step="0.1" value="1" style="width: 100%; height: 4px; background: var(--dl-rule); border-radius: 2px; outline: none;" aria-valuemin="0.5" aria-valuemax="2" aria-valuenow="1" aria-label="Narration speed">
                            <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--dl-gray-2); margin-top: 2px;">
                                <span>0.5x</span>
                                <span>1x</span>
                                <span>2x</span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="narrator-text-preview" style="background: var(--dl-aurora-bg); border-radius: 6px; padding: 12px; max-height: 120px; overflow-y: auto; font-size: 13px; line-height: 1.4; color: var(--dl-contrast); border: 1px solid var(--dl-rule);">
                        <div id="narrator-stats" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--dl-teal-deep); margin-bottom: 8px;"></div>
                        <div id="narrator-text-content">Click play to start reading...</div>
                    </div>
                </div>
            </div>
            
            <!-- Playing State -->
            <div id="narrator-playing" class="narrator-state" style="display: none;">
                <div style="background: var(--dl-aurora-bg); border-radius: 8px; padding: 16px; border: 1px solid rgba(15, 90, 65, 0.16); width: 300px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div>
                                <div style="font-weight: 500; font-size: 14px; color: var(--dl-teal-ink);">Narration</div>
                                <div style="font-size: 12px; color: var(--dl-dusty-emerald);">● Playing</div>
                            </div>
                        </div>
                        <button id="narrator-minimize-btn" class="narrator-focus" style="background: none; border: none; color: var(--dl-gray-1); cursor: pointer; font-size: 18px;"
                            aria-label="Minimize Narrator controls">−</button>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <button id="narrator-pause" class="narrator-focus" style="background: var(--dl-dusty-emerald); color: #fff; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;"
                                aria-label="Pause page narration">
                                ⏸ Pause
                            </button>
                            <button id="narrator-stop-playing" class="narrator-focus" style="background: transparent; color: var(--dl-teal-ink); box-shadow: inset 0 0 0 1px rgba(15, 90, 65, 0.2); border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;"
                                aria-label="Stop page narration">
                                ⏹ Stop
                            </button>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <span id="narrator-current-time-playing" style="font-size: 12px; color: var(--dl-gray-1); min-width: 40px;" aria-live="polite">0:00</span>
                            <div style="flex: 1; height: 4px; background: var(--dl-rule); border-radius: 2px; position: relative;">
                                <div id="narrator-progress-playing" style="height: 100%; background: var(--dl-dusty-emerald); border-radius: 2px; width: 0%; transition: width 0.1s;"></div>
                            </div>
                            <span id="narrator-total-time-playing" style="font-size: 12px; color: var(--dl-gray-1); min-width: 40px;">0:00</span>
                        </div>
                        
                        <div style="margin-top: 12px;">
                            <label for="narrator-speed-playing" style="font-size: 12px; color: var(--dl-gray-1); display: block; margin-bottom: 4px;">Speed:</label>
                            <input type="range" id="narrator-speed-playing" min="0.5" max="2" step="0.1" value="1" style="width: 100%; height: 4px; background: var(--dl-rule); border-radius: 2px; outline: none;" aria-valuemin="0.5" aria-valuemax="2" aria-valuenow="1" aria-label="Narration speed">
                            <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--dl-gray-2); margin-top: 2px;">
                                <span>0.5x</span>
                                <span>1x</span>
                                <span>2x</span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="narrator-text-preview-playing" style="background: rgba(37, 159, 152, 0.08); border-radius: 6px; padding: 12px; max-height: 120px; overflow-y: auto; font-size: 13px; line-height: 1.4; color: var(--dl-contrast); border: 1px solid rgba(37, 159, 152, 0.25);">
                        <div id="narrator-stats-playing" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--dl-teal-deep); margin-bottom: 8px;"></div>
                        <div id="narrator-text-content-playing">Reading page content...</div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        (function() {
            let currentState = 'collapsed';
            let speechSynthesis = window.speechSynthesis;
            let currentUtterance = null;
            let isPlaying = false;
            let pageContent = '';
            let currentPosition = 0;
            let pausedElapsed = 0;
            let totalDuration = 0;
            let startTime = 0;
            let progressInterval = null;
            
            const states = {
                collapsed: document.getElementById('narrator-collapsed'),
                hover: document.getElementById('narrator-hover'),
                expanded: document.getElementById('narrator-expanded'),
                playing: document.getElementById('narrator-playing')
            };
            
            function setState(newState) {
                Object.keys(states).forEach(key => {
                    states[key].style.display = key === newState ? 'block' : 'none';
                });
                currentState = newState;
            }
            
            function extractPageContent() {
                const contentSelectors = [
                    'main', 'article', '.content', '.post-content', '.entry-content',
                    '.page-content', '#content', '.main-content', '[role="main"]'
                ];
                
                let contentElement = null;
                for (const selector of contentSelectors) {
                    contentElement = document.querySelector(selector);
                    if (contentElement) break;
                }
                
                if (!contentElement) contentElement = document.body;
                
                const textSelectors = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote'];
                const textElements = contentElement.querySelectorAll(textSelectors.join(', '));
                const textArray = [];
                
                textElements.forEach(element => {
                    const parentClasses = element.closest('[class]')?.className || '';
                    const skipPatterns = [
                        'nav', 'menu', 'header', 'footer', 'sidebar', 'widget', 
                        'advertisement', 'ad-', 'social', 'share', 'comment',
                        'breadcrumb', 'pagination', 'tag', 'category', 'meta'
                    ];
                    
                    const shouldSkip = skipPatterns.some(pattern => 
                        parentClasses.toLowerCase().includes(pattern.toLowerCase())
                    );
                    
                    if (!shouldSkip) {
                        const text = element.textContent?.trim();
                        if (text && text.length > 15 && !textArray.includes(text)) {
                            textArray.push(text);
                        }
                    }
                });
                
                return textArray.join(' ');
            }
            
            function formatTime(seconds) {
                const mins = Math.floor(seconds / 60);
                const secs = Math.floor(seconds % 60);
                return mins + ':' + secs.toString().padStart(2, '0');
            }
            
            function updateProgress() {
                if (!isPlaying || !currentUtterance) return;
                
                const elapsed = pausedElapsed + (Date.now() - startTime) / 1000;
                const progress = Math.min(elapsed / totalDuration, 1) * 100;
                
                const progressBars = [
                    document.getElementById('narrator-progress'),
                    document.getElementById('narrator-progress-playing')
                ];
                
                const currentTimeElements = [
                    document.getElementById('narrator-current-time'),
                    document.getElementById('narrator-current-time-playing')
                ];
                
                progressBars.forEach(bar => {
                    if (bar) bar.style.width = progress + '%';
                });
                
                currentTimeElements.forEach(element => {
                    if (element) element.textContent = formatTime(elapsed);
                });
            }
            
            function startReading() {
                pausedElapsed = 0;
                currentPosition = 0;

                if (!pageContent) {
                    pageContent = extractPageContent();
                }
                
                if (!pageContent) {
                    alert('No readable content found on this page.');
                    return;
                }
                
                const wordCount = pageContent.split(/\s+/).filter(w => w.length > 0).length;
                const readingTime = Math.ceil(wordCount / 200);
                
                // Update stats in both states
                const statsElements = [
                    document.getElementById('narrator-stats'),
                    document.getElementById('narrator-stats-playing')
                ];
                
                statsElements.forEach(element => {
                    if (element) element.textContent = wordCount + ' words • ' + readingTime + ' min read';
                });
                
                // Update text content preview
                const textElements = [
                    document.getElementById('narrator-text-content'),
                    document.getElementById('narrator-text-content-playing')
                ];
                
                const preview = pageContent.substring(0, 200) + (pageContent.length > 200 ? '...' : '');
                textElements.forEach(element => {
                    if (element) element.textContent = preview;
                });
                
                // Calculate estimated duration (150 words per minute average)
                totalDuration = (wordCount / 150) * 60;
                
                const totalTimeElements = [
                    document.getElementById('narrator-total-time'),
                    document.getElementById('narrator-total-time-playing')
                ];
                
                totalTimeElements.forEach(element => {
                    if (element) element.textContent = formatTime(totalDuration);
                });
                
                // Create and start speech synthesis
                currentUtterance = new SpeechSynthesisUtterance(pageContent);
                currentUtterance.rate = parseFloat(document.getElementById('narrator-speed').value) || 1;
                currentUtterance.pitch = 1;
                currentUtterance.volume = 1;

                // Tracks how far into pageContent speech actually is, so a mid-read
                // speed change (or a future pause/resume) can pick up from here
                // instead of jumping back to character 0.
                currentUtterance.onboundary = function(event) {
                    currentPosition = event.charIndex;
                };
                
                currentUtterance.onend = function() {
                    isPlaying = false;
                    setState('expanded');
                };
                
                currentUtterance.onerror = function(event) {
                    console.error('Speech synthesis error:', event.error);
                    isPlaying = false;
                    setState('expanded');
                };
                
                speechSynthesis.speak(currentUtterance);
                isPlaying = true;
                startTime = Date.now();
                setState('playing');
                
                // Start progress tracking
                startProgressInterval();
            }

            function startProgressInterval() {
                if (progressInterval) clearInterval(progressInterval);
                progressInterval = setInterval(() => {
                    if (isPlaying) {
                        updateProgress();
                    } else {
                        clearInterval(progressInterval);
                        progressInterval = null;
                    }
                }, 100);
            }
            
            function pauseReading() {
                if (speechSynthesis.speaking) {
                    pausedElapsed += (Date.now() - startTime) / 1000;
                    speechSynthesis.pause();
                    isPlaying = false;
                    setState('expanded');
                }
            }

            function resumeReading() {
                if (currentUtterance && speechSynthesis.paused) {
                    speechSynthesis.resume();
                    isPlaying = true;
                    startTime = Date.now();
                    setState('playing');
                    startProgressInterval();
                } else {
                    // Nothing paused to resume (first play, or already finished) -- start fresh.
                    startReading();
                }
            }
            
            function stopReading() {
                if (speechSynthesis.speaking || speechSynthesis.paused) {
                    speechSynthesis.cancel();
                }
                isPlaying = false;
                currentUtterance = null;
                currentPosition = 0;
                pausedElapsed = 0;
                setState('expanded');
            }
            
            // Event listeners
            states.collapsed.addEventListener('mouseenter', () => {
                if (currentState === 'collapsed') {
                    setState('hover');
                }
            });
            
            states.hover.addEventListener('mouseleave', () => {
                if (currentState === 'hover') {
                    setState('collapsed');
                }
            });
            
            const headphonesToggle = document.querySelector('.narrator-headphones');

            function openExpanded() {
                setState('expanded');
                const firstControl = document.getElementById('narrator-play-pause');
                if (firstControl) firstControl.focus();
            }

            function collapseAndReturnFocus() {
                setState('collapsed');
                if (headphonesToggle) headphonesToggle.focus();
            }

            if (headphonesToggle) {
                headphonesToggle.addEventListener('click', openExpanded);
                headphonesToggle.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                        e.preventDefault();
                        openExpanded();
                    }
                });
            }

            document.getElementById('narrator-play-btn').addEventListener('click', () => {
                setState('expanded');
            });
            
            document.getElementById('narrator-close-btn').addEventListener('click', collapseAndReturnFocus);
            
            document.getElementById('narrator-minimize-btn').addEventListener('click', collapseAndReturnFocus);
            
            document.getElementById('narrator-play-pause').addEventListener('click', resumeReading);
            document.getElementById('narrator-pause').addEventListener('click', pauseReading);
            document.getElementById('narrator-stop').addEventListener('click', stopReading);
            document.getElementById('narrator-stop-playing').addEventListener('click', stopReading);
            
            // Speed control
            const speedControls = [
                document.getElementById('narrator-speed'),
                document.getElementById('narrator-speed-playing')
            ];
            
            speedControls.forEach(control => {
                if (control) {
                    control.addEventListener('input', (e) => {
                        const newRate = parseFloat(e.target.value);
                        if (currentUtterance) {
                            // Update both sliders
                            speedControls.forEach(slider => {
                                if (slider) slider.value = newRate;
                            });
                            
                            // Apply new rate
                            if (isPlaying) {
                                const resumeFrom = currentPosition;
                                speechSynthesis.cancel();
                                pausedElapsed += (Date.now() - startTime) / 1000;
                                const remainingText = pageContent.substring(resumeFrom);
                                currentUtterance = new SpeechSynthesisUtterance(remainingText);
                                currentUtterance.rate = newRate;
                                currentUtterance.pitch = 1;
                                currentUtterance.volume = 1;

                                currentUtterance.onboundary = function(event) {
                                    currentPosition = resumeFrom + event.charIndex;
                                };
                                
                                currentUtterance.onend = function() {
                                    isPlaying = false;
                                    setState('expanded');
                                };

                                currentUtterance.onerror = function(event) {
                                    console.error('Speech synthesis error:', event.error);
                                    isPlaying = false;
                                    setState('expanded');
                                };
                                
                                speechSynthesis.speak(currentUtterance);
                                startTime = Date.now();
                                startProgressInterval();
                            }
                        }
                    });
                }
            });
            
            // Initialize
            setState('collapsed');
        })();
        </script>
        <?php
    }
    
    public function extract_page_content() {
        // This function could be used for AJAX requests if needed
        wp_die();
    }
}

// Initialize the plugin
new NarratorPlugin();
?>