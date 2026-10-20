@props(['title'=>'No jobs here yet','message'=>'Once a configured source has synchronized, relevant opportunities will appear here.','icon'=>'search'])
<div class="empty"><x-icon :name="$icon"/><h3>{{ $title }}</h3><p>{{ $message }}</p>{{ $slot }}</div>
