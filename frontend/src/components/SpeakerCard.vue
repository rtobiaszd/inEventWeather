<template>
  <div class="speaker-card" :class="{ 'speaker-card-featured': featured }">
    <div class="speaker-avatar">
      <img v-if="speaker.avatar_url" :src="speaker.avatar_url" :alt="speaker.name" />
      <span v-else class="avatar-placeholder">{{ initials }}</span>
    </div>
    <div class="speaker-info">
      <h4>{{ speaker.name }}</h4>
      <p v-if="speaker.role_title || speaker.company" class="speaker-role">
        {{ speaker.role_title }}{{ speaker.role_title && speaker.company ? ' — ' : '' }}{{ speaker.company }}
      </p>
      <p v-if="speaker.expertise" class="speaker-expertise">{{ speaker.expertise }}</p>
      <p v-if="speaker.bio" class="speaker-bio">{{ speaker.bio }}</p>
      <div v-if="speaker.social_linkedin || speaker.social_twitter || speaker.website" class="speaker-social">
        <a v-if="speaker.social_linkedin" :href="speaker.social_linkedin" target="_blank" rel="noopener" class="social-link" title="LinkedIn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
        </a>
        <a v-if="speaker.social_twitter" :href="speaker.social_twitter" target="_blank" rel="noopener" class="social-link" title="Twitter/X">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        </a>
        <a v-if="speaker.website" :href="speaker.website" target="_blank" rel="noopener" class="social-link" title="Website">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        </a>
      </div>
    </div>
    <div v-if="$slots.actions" class="speaker-actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  speaker: { type: Object, required: true },
  featured: { type: Boolean, default: false },
})

const initials = computed(() => {
  const parts = props.speaker.name?.split(' ') || []
  return parts.slice(0, 2).map(p => p[0]).join('').toUpperCase()
})
</script>

<style scoped>
.speaker-card {
  display: flex;
  gap: 16px;
  padding: 16px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  align-items: flex-start;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.speaker-card:hover {
  border-color: var(--color-primary);
}

.speaker-card-featured {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 1px var(--color-primary);
}

.speaker-avatar {
  flex-shrink: 0;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--color-bg);
  display: flex;
  align-items: center;
  justify-content: center;
}

.speaker-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-placeholder {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-text-secondary);
}

.speaker-info {
  flex: 1;
  min-width: 0;
}

.speaker-info h4 {
  font-size: 15px;
  font-weight: 700;
  margin: 0 0 2px;
}

.speaker-role {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin: 0 0 4px;
}

.speaker-expertise {
  font-size: 11px;
  color: var(--color-primary);
  font-weight: 600;
  margin: 0 0 4px;
}

.speaker-bio {
  font-size: 12px;
  color: var(--color-text-secondary);
  line-height: 1.5;
  margin: 4px 0 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.speaker-social {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}

.social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  color: var(--color-text-secondary);
  background: var(--color-bg);
  transition: color 0.15s, background 0.15s;
}

.social-link:hover {
  color: var(--color-primary);
  background: var(--color-border);
}

.speaker-actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
}
</style>
